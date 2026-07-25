<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Actions;

use App\Domain\Administration\Services\BusinessReferenceGenerator;
use App\Domain\Checkout\Data\PlaceOrderCommand;
use App\Domain\Checkout\Data\PlaceOrderResult;
use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Pricing\Services\PricingEngine;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\ProductVariant;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Correlation\CorrelationContext;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Exceptions\ConflictException;
use App\Support\Idempotency\CheckoutIdempotencyService;
use App\Support\Money\Money;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent, atomic order placement — the heart of the platform
 * (FR-CHK-007/008/009/010, BR-002/006/016, prompt §12, LLD §6.1 / Figure 6).
 *
 * Sequence:
 *  1. Resolve the idempotency key (replay → same result; different fingerprint → 409).
 *  2. Load the checkout session and its cart items.
 *  3. In one DB transaction: revalidate product eligibility, reprice from
 *     authoritative data, and reserve stock atomically in a deterministic
 *     (variant-id ascending) order to prevent oversell and deadlocks.
 *  4. Create the immutable order + item + address snapshots (BR-002).
 *  5. Record audit evidence and an OrderPlaced outbox event in the same commit.
 *  6. Mark the idempotency record completed with the stored result.
 * Payment creation happens AFTER commit (Figure 6 step 8), never inside this txn.
 */
final class PlaceOrderAction
{
    public function __construct(
        private readonly CheckoutIdempotencyService $idempotency,
        private readonly PricingEngine $pricing,
        private readonly InventoryReservationService $inventory,
        private readonly BusinessReferenceGenerator $references,
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(PlaceOrderCommand $command): PlaceOrderResult
    {
        $actorKey = $command->actor->userPublicId ?? ('guest:'.$command->checkoutPublicId);

        // (1) Idempotency resolution.
        $resolution = $this->idempotency->resolve($command->idempotencyKey, $actorKey, $command->requestFingerprint);
        if ($resolution->isConflict) {
            throw new ConflictException(
                'checkout.idempotency_conflict',
                'This idempotency key was already used with a different request.',
            );
        }
        if ($resolution->isReplay && $resolution->storedResponse !== null) {
            return PlaceOrderResult::fromStored($resolution->storedResponse);
        }

        // (2) Load checkout + cart.
        $checkout = DB::table('checkout_sessions')->where('public_id', $command->checkoutPublicId)->first();
        if ($checkout === null) {
            throw new BusinessRuleException('checkout.incomplete', 'Checkout session not found.');
        }
        if (! $checkout->terms_accepted) {
            throw new BusinessRuleException('checkout.incomplete', 'Terms must be accepted before placing the order.');
        }

        $cartItems = DB::table('cart_items')
            ->where('cart_id', $checkout->cart_id)
            ->orderBy('product_variant_id') // deterministic lock order (prompt §12)
            ->get();

        if ($cartItems->isEmpty()) {
            throw new BusinessRuleException('checkout.incomplete', 'The cart is empty.');
        }

        $currency = (string) $checkout->currency;
        $ttl = (int) config('iocms.reservations.ttl_minutes', 30);

        $result = DB::transaction(function () use ($command, $checkout, $cartItems, $currency, $ttl): PlaceOrderResult {
            // (3) Revalidate + reprice + reserve, deterministically ordered.
            $lines = [];
            $reservationIds = [];
            $snapshotRows = [];

            foreach ($cartItems as $ci) {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()->with('product')->find($ci->product_variant_id);
                if ($variant === null || $variant->status !== 'active') {
                    throw new BusinessRuleException('checkout.inventory_unavailable', 'An item is no longer available.');
                }
                if ($variant->product?->status !== ProductStatus::Published) {
                    throw new BusinessRuleException('checkout.inventory_unavailable', 'An item is no longer purchasable.');
                }

                // Authoritative price (latest active price for the variant).
                $priceRow = DB::table('prices')
                    ->where('product_variant_id', $variant->id)
                    ->where('status', 'active')
                    ->orderByDesc('effective_from')
                    ->first();
                if ($priceRow === null) {
                    throw new BusinessRuleException('pricing.no_active_price', 'No active price for an item.');
                }
                $unit = Money::fromDecimalString((string) $priceRow->amount, $currency);

                // Reserve stock atomically (throws checkout.inventory_unavailable on oversell).
                $reservationIds[] = $this->inventory->reserve(
                    variantId: $variant->id,
                    quantity: (int) $ci->quantity,
                    holderType: 'order',
                    holderReference: $command->checkoutPublicId,
                    ttlMinutes: $ttl,
                );

                $lines[] = [
                    'variant_public_id' => $variant->public_id,
                    'quantity' => (int) $ci->quantity,
                    'unit_price' => $unit,
                ];
                $snapshotRows[] = ['ci' => $ci, 'variant' => $variant, 'unit' => $unit];
            }

            // Reprice deterministically from authoritative inputs.
            $quote = $this->pricing->quote(lines: $lines, currency: $currency);

            // (4) Immutable order snapshot.
            $orderNumber = $this->references->next('order');
            $order = Order::query()->create([
                'public_id' => PublicId::generate(),
                'order_number' => $orderNumber,
                'user_id' => $command->actor->userId,
                'guest_email' => $command->actor->userId ? null : $checkout->contact_email,
                'status' => OrderStatus::PendingPayment->value,
                'currency' => $currency,
                'items_subtotal' => $quote->itemsSubtotal->toDecimalString(),
                'discount_total' => $quote->discountTotal->toDecimalString(),
                'tax_total' => $quote->taxTotal->toDecimalString(),
                'delivery_total' => $quote->deliveryTotal->toDecimalString(),
                'grand_total' => $quote->grandTotal->toDecimalString(),
                'payment_method' => $checkout->payment_method,
                'delivery_method' => $checkout->delivery_method,
                'terms_version' => $checkout->terms_version,
                'quote_snapshot' => $quote->toArray(),
                'lock_version' => 0,
                'correlation_id' => CorrelationContext::id(),
            ]);

            foreach ($snapshotRows as $i => $row) {
                $lineQuote = $quote->lines[$i];
                $policy = DB::table('product_policy_metadata')->where('product_id', $row['variant']->product_id)->first();
                OrderItem::query()->create([
                    'public_id' => PublicId::generate(),
                    'order_id' => $order->id,
                    'product_variant_id' => $row['variant']->id,
                    'product_name' => $row['variant']->product?->translations()->where('locale', 'en')->value('name') ?? $row['variant']->sku,
                    'sku' => $row['variant']->sku,
                    'unit_price' => $row['unit']->toDecimalString(),
                    'discount' => $lineQuote->discount->toDecimalString(),
                    'tax' => $lineQuote->tax->toDecimalString(),
                    'line_total' => $lineQuote->lineTotal->toDecimalString(),
                    'currency' => $currency,
                    'quantity_ordered' => $lineQuote->quantity,
                    'return_policy_version' => $policy->policy_version ?? 1,
                    'return_window_days' => $policy->return_window_days ?? null,
                ]);
            }

            // Rebind stock reservations to the order reference for later consume/release.
            DB::table('stock_reservations')
                ->whereIn('public_id', $reservationIds)
                ->update(['holder_type' => 'order', 'holder_reference' => $order->order_number]);

            DB::table('order_status_events')->insert([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment->value,
                'reason' => 'placed',
                'correlation_id' => CorrelationContext::id(),
                'created_at' => now(),
            ]);

            DB::table('checkout_sessions')->where('id', $checkout->id)->update(['status' => 'placed', 'updated_at' => now()]);
            DB::table('carts')->where('id', $checkout->cart_id)->update(['status' => 'converted', 'updated_at' => now()]);

            // (5) Audit + outbox in the same commit.
            $this->audit->record(
                action: 'order.placed',
                resourceType: 'order',
                resourcePublicId: $order->public_id,
                after: ['order_number' => $orderNumber, 'grand_total' => $quote->grandTotal->toDecimalString()],
            );

            $this->outbox->record(
                eventName: 'OrderPlaced',
                aggregateType: 'order',
                aggregatePublicId: $order->public_id,
                payload: [
                    'order_public_id' => $order->public_id,
                    'order_number' => $orderNumber,
                    'grand_total' => $quote->grandTotal->toDecimalString(),
                    'currency' => $currency,
                    'payment_method' => $checkout->payment_method,
                ],
            );

            return new PlaceOrderResult(
                orderPublicId: $order->public_id,
                orderNumber: $orderNumber,
                status: OrderStatus::PendingPayment->value,
                grandTotal: $quote->grandTotal->toDecimalString(),
                currency: $currency,
            );
        });

        // (6) Persist the idempotent replay result (Figure 6 step 7).
        $this->idempotency->complete($command->idempotencyKey, $actorKey, $result->toArray());

        return $result;
    }
}
