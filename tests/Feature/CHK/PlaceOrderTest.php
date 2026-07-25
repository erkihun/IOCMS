<?php

declare(strict_types=1);

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Checkout\Data\PlaceOrderCommand;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductTranslation;
use App\Domain\Product\Models\ProductVariant;
use App\Support\Correlation\ActorContext;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Exceptions\ConflictException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-CHK-007/008/009/010, BR-006/016, prompt §12/§18 — idempotent atomic order
 * placement, no oversell, idempotency replay + fingerprint-mismatch.
 * Test basis: T-FR-CHK-008, concurrent-final-stock, duplicate-checkout.
 */
beforeEach(function () {
    $this->action = app(PlaceOrderAction::class);
    $this->inventory = app(InventoryReservationService::class);
});

/**
 * Build a published product+variant with a price and `stock` on-hand units,
 * plus a cart and an open checkout session ready to place. Returns [checkoutPublicId, variant].
 */
function setupCheckout(int $stock = 5, int $qty = 1, string $price = '100.00'): array
{
    $product = Product::query()->create(['public_id' => PublicId::generate(), 'status' => ProductStatus::Published->value, 'type' => 'standard']);
    ProductTranslation::query()->create(['product_id' => $product->id, 'locale' => 'en', 'name' => 'Widget', 'slug' => 'widget-'.$product->id]);
    $variant = ProductVariant::query()->create(['public_id' => PublicId::generate(), 'product_id' => $product->id, 'sku' => 'SKU-'.$product->id, 'status' => 'active', 'is_default' => true]);
    DB::table('product_policy_metadata')->insert(['product_id' => $product->id, 'return_window_days' => 30, 'is_returnable' => true, 'tax_class' => 'standard', 'policy_version' => 1, 'created_at' => now(), 'updated_at' => now()]);

    $priceList = DB::table('price_lists')->insertGetId(['public_id' => PublicId::generate(), 'code' => 'pl-'.$product->id, 'currency' => 'ETB', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('prices')->insert(['price_list_id' => $priceList, 'product_variant_id' => $variant->id, 'amount' => $price, 'currency' => 'ETB', 'status' => 'active', 'effective_from' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()]);

    app(InventoryReservationService::class)->setOnHand($variant->id, $stock);

    $cartId = DB::table('carts')->insertGetId(['public_id' => PublicId::generate(), 'currency' => 'ETB', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_variant_id' => $variant->id, 'quantity' => $qty, 'currency' => 'ETB', 'created_at' => now(), 'updated_at' => now()]);

    $checkoutPublicId = PublicId::generate();
    DB::table('checkout_sessions')->insert([
        'public_id' => $checkoutPublicId, 'cart_id' => $cartId, 'status' => 'open',
        'contact_email' => 'buyer@example.com', 'currency' => 'ETB', 'payment_method' => 'cod',
        'terms_accepted' => true, 'terms_version' => 'v1', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$checkoutPublicId, $variant];
}

function guestCommand(string $checkoutId, string $key, string $fingerprint): PlaceOrderCommand
{
    return new PlaceOrderCommand($checkoutId, $key, $fingerprint, new ActorContext(userId: null, type: 'guest'));
}

it('places an order atomically with an immutable snapshot and reserves stock', function () {
    [$checkoutId, $variant] = setupCheckout(stock: 5, qty: 2, price: '100.00');

    $result = $this->action->execute(guestCommand($checkoutId, 'key-1', 'fp-1'));

    expect($result->orderNumber)->toStartWith('ORD-')
        ->and($result->status)->toBe('pending_payment')
        ->and($result->grandTotal)->toBe('200.0000');

    $order = Order::query()->where('public_id', $result->orderPublicId)->firstOrFail();
    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity_ordered)->toBe(2);

    // Reservation reduced available but not on_hand (BR-006).
    $item = DB::table('inventory_items')->where('product_variant_id', $variant->id)->first();
    expect($item->available)->toBe(3)->and($item->on_hand)->toBe(5)->and($item->reserved)->toBe(2);

    // Outbox event recorded in the same transaction.
    expect(DB::table('outbox_events')->where('event_name', 'OrderPlaced')->count())->toBe(1);
});

it('returns the same result for a replayed identical request (idempotency)', function () {
    [$checkoutId] = setupCheckout();

    $first = $this->action->execute(guestCommand($checkoutId, 'key-2', 'fp-2'));
    $second = $this->action->execute(guestCommand($checkoutId, 'key-2', 'fp-2'));

    expect($second->orderPublicId)->toBe($first->orderPublicId)
        ->and($second->replayed)->toBeTrue();
    // Exactly one order created despite two calls.
    expect(Order::query()->count())->toBe(1);
});

it('rejects reuse of an idempotency key with a different fingerprint (409)', function () {
    [$checkoutId] = setupCheckout();

    $this->action->execute(guestCommand($checkoutId, 'key-3', 'fp-A'));

    expect(fn () => $this->action->execute(guestCommand($checkoutId, 'key-3', 'fp-B')))
        ->toThrow(ConflictException::class);
});

it('does not oversell the final unit under contention', function () {
    // Only 1 unit on hand; two separate checkouts each want 1.
    [$checkoutA, $variant] = setupCheckout(stock: 1, qty: 1);
    // Second checkout for the SAME variant.
    $cartB = DB::table('carts')->insertGetId(['public_id' => PublicId::generate(), 'currency' => 'ETB', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert(['cart_id' => $cartB, 'product_variant_id' => $variant->id, 'quantity' => 1, 'currency' => 'ETB', 'created_at' => now(), 'updated_at' => now()]);
    $checkoutB = PublicId::generate();
    DB::table('checkout_sessions')->insert(['public_id' => $checkoutB, 'cart_id' => $cartB, 'status' => 'open', 'contact_email' => 'b@example.com', 'currency' => 'ETB', 'payment_method' => 'cod', 'terms_accepted' => true, 'terms_version' => 'v1', 'created_at' => now(), 'updated_at' => now()]);

    $this->action->execute(guestCommand($checkoutA, 'key-A', 'fp-A'));

    // The second order cannot reserve the already-taken unit.
    expect(fn () => $this->action->execute(guestCommand($checkoutB, 'key-B', 'fp-B')))
        ->toThrow(BusinessRuleException::class);

    expect(Order::query()->count())->toBe(1);
    $item = DB::table('inventory_items')->where('product_variant_id', $variant->id)->first();
    expect($item->available)->toBe(0)->and($item->reserved)->toBe(1); // never negative (BR-008)
});

it('rejects placement when terms are not accepted', function () {
    [$checkoutId] = setupCheckout();
    DB::table('checkout_sessions')->where('public_id', $checkoutId)->update(['terms_accepted' => false]);

    expect(fn () => $this->action->execute(guestCommand($checkoutId, 'key-4', 'fp-4')))
        ->toThrow(BusinessRuleException::class);
});
