<?php

declare(strict_types=1);

use App\Domain\Marketplace\Actions\ApprovePayoutAction;
use App\Domain\Marketplace\Actions\PostCommissionAction;
use App\Domain\Marketplace\Services\SellerScopeGuard;
use App\Support\Exceptions\AuthorizationException;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Exceptions\ConflictException;
use App\Support\Money\Money;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Wave 6 marketplace controls (prompt §18): duplicate commission posting, payout
 * approval separation, seller cross-scope isolation (security boundary).
 */
function seed_seller(): int
{
    return (int) DB::table('sellers')->insertGetId(['public_id' => PublicId::generate(), 'legal_name' => 'S', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
}

function seed_order_item(): int
{
    $order = DB::table('orders')->insertGetId(['public_id' => PublicId::generate(), 'order_number' => 'ORD-M-'.random_int(1000, 9999), 'status' => 'confirmed', 'currency' => 'ETB', 'grand_total' => '100.0000', 'created_at' => now(), 'updated_at' => now()]);

    return (int) DB::table('order_items')->insertGetId(['public_id' => PublicId::generate(), 'order_id' => $order, 'product_name' => 'X', 'sku' => 'S1', 'unit_price' => '100.0000', 'line_total' => '100.0000', 'currency' => 'ETB', 'quantity_ordered' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

it('snapshots commission once and rejects a duplicate posting (BR-028)', function () {
    $seller = seed_seller();
    $item = seed_order_item();
    $action = app(PostCommissionAction::class);

    $action->execute($item, $seller, '0.10', Money::fromDecimalString('100.00', 'ETB'));

    expect(DB::table('commission_snapshots')->where('order_item_id', $item)->count())->toBe(1);
    expect((float) DB::table('commission_snapshots')->where('order_item_id', $item)->value('commission_amount'))->toBe(10.0);

    // Duplicate posting for the same order line is rejected.
    expect(fn () => $action->execute($item, $seller, '0.10', Money::fromDecimalString('100.00', 'ETB')))
        ->toThrow(ConflictException::class);
});

it('forbids a payout maker from approving their own payout (BR-024)', function () {
    $seller = seed_seller();
    DB::table('seller_ledger_entries')->insert(['public_id' => PublicId::generate(), 'seller_id' => $seller, 'type' => 'sale', 'bucket' => 'available', 'amount' => '500.0000', 'currency' => 'ETB', 'source_type' => 'x', 'source_reference' => 'x1', 'posted_at' => now()]);
    $payout = PublicId::generate();
    DB::table('seller_payouts')->insert(['public_id' => $payout, 'payout_number' => 'PAY-1', 'seller_id' => $seller, 'status' => 'pending_approval', 'amount' => '100.0000', 'currency' => 'ETB', 'maker_id' => 7, 'idempotency_key' => 'pk-1', 'created_at' => now(), 'updated_at' => now()]);

    $action = app(ApprovePayoutAction::class);

    // Maker (7) cannot approve.
    expect(fn () => $action->execute($payout, 7))->toThrow(AuthorizationException::class);

    // A different checker (8) can.
    $action->execute($payout, 8);
    expect(DB::table('seller_payouts')->where('public_id', $payout)->value('status'))->toBe('approved');
});

it('rejects a payout exceeding the available balance (BR-030)', function () {
    $seller = seed_seller();
    DB::table('seller_ledger_entries')->insert(['public_id' => PublicId::generate(), 'seller_id' => $seller, 'type' => 'sale', 'bucket' => 'available', 'amount' => '50.0000', 'currency' => 'ETB', 'source_type' => 'x', 'source_reference' => 'x1', 'posted_at' => now()]);
    $payout = PublicId::generate();
    DB::table('seller_payouts')->insert(['public_id' => $payout, 'payout_number' => 'PAY-2', 'seller_id' => $seller, 'status' => 'pending_approval', 'amount' => '100.0000', 'currency' => 'ETB', 'maker_id' => 7, 'idempotency_key' => 'pk-2', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => app(ApprovePayoutAction::class)->execute($payout, 8))
        ->toThrow(BusinessRuleException::class);
});

it('isolates one seller from another seller records (BR-032 security boundary)', function () {
    $sellerA = seed_seller();
    $sellerB = seed_seller();
    // A real user belongs to seller A only.
    $userId = (int) DB::table('users')->insertGetId(['public_id' => PublicId::generate(), 'type' => 'seller', 'status' => 'active', 'locale' => 'en', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('seller_users')->insert(['seller_id' => $sellerA, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

    $guard = app(SellerScopeGuard::class);

    // Access to own seller passes.
    $guard->assertCanAccessSeller($userId, $sellerA);

    // Access to another seller is denied.
    expect(fn () => $guard->assertCanAccessSeller($userId, $sellerB))
        ->toThrow(AuthorizationException::class);
});
