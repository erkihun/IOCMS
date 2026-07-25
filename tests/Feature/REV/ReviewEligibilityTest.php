<?php

declare(strict_types=1);

use App\Domain\Engagement\Actions\SubmitReviewAction;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-REV-001/002, BR-013 — verified-purchase reviews (prompt §18 review boundary).
 */
beforeEach(fn () => $this->action = app(SubmitReviewAction::class));

function makeReviewUser(): int
{
    return (int) DB::table('users')->insertGetId([
        'public_id' => PublicId::generate(), 'type' => 'customer', 'status' => 'active',
        'locale' => 'en', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function deliveredOrderItem(int $userId, string $orderStatus = 'delivered'): OrderItem
{
    $product = Product::query()->create(['public_id' => PublicId::generate(), 'status' => ProductStatus::Published->value, 'type' => 'standard']);
    $variant = ProductVariant::query()->create(['public_id' => PublicId::generate(), 'product_id' => $product->id, 'sku' => 'R-'.$product->id, 'status' => 'active', 'is_default' => true]);
    $order = Order::query()->create(['public_id' => PublicId::generate(), 'order_number' => 'ORD-V-'.random_int(1000, 99999), 'user_id' => $userId, 'status' => $orderStatus, 'currency' => 'ETB', 'grand_total' => '10.0000', 'lock_version' => 0]);

    return OrderItem::query()->create([
        'public_id' => PublicId::generate(), 'order_id' => $order->id, 'product_variant_id' => $variant->id,
        'product_name' => 'X', 'sku' => 'R-'.$product->id, 'unit_price' => '10.0000', 'line_total' => '10.0000',
        'currency' => 'ETB', 'quantity_ordered' => 1, 'quantity_fulfilled' => 1,
    ]);
}

it('allows a verified-purchase review on a delivered order line', function () {
    $user = makeReviewUser();
    $item = deliveredOrderItem($user);
    $id = $this->action->execute($user, $item->id, 5, 'Great', 'Loved it');

    expect($id)->toBeUuid();
    expect(DB::table('reviews')->where('public_id', $id)->where('verified_purchase', true)->where('status', 'pending')->exists())->toBeTrue();
});

it('rejects a review when the order is not delivered/completed', function () {
    $user = makeReviewUser();
    $item = deliveredOrderItem($user, OrderStatus::Confirmed->value);
    expect(fn () => $this->action->execute($user, $item->id, 5, null, null))->toThrow(BusinessRuleException::class);
});

it('rejects a review from a customer who does not own the order line', function () {
    $owner = makeReviewUser();
    $other = makeReviewUser();
    $item = deliveredOrderItem($owner);
    expect(fn () => $this->action->execute($other, $item->id, 5, null, null))->toThrow(BusinessRuleException::class);
});

it('prevents a duplicate review for the same order line (BR-013)', function () {
    $user = makeReviewUser();
    $item = deliveredOrderItem($user);
    $this->action->execute($user, $item->id, 4, null, null);

    expect(fn () => $this->action->execute($user, $item->id, 5, null, null))->toThrow(BusinessRuleException::class);
});
