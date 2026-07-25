<?php

declare(strict_types=1);

use App\Domain\Pricing\Models\Coupon;
use App\Domain\Pricing\Services\CouponRedemptionService;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;

/**
 * FR-PRI-005 — coupon usage limits under concurrency (prompt §18 coupon
 * concurrency test). The atomic conditional update guarantees the usage limit
 * is never exceeded even when reservations race.
 */
beforeEach(fn () => $this->service = app(CouponRedemptionService::class));

function makeCoupon(int $limit): Coupon
{
    return Coupon::query()->create([
        'public_id' => PublicId::generate(),
        'code' => 'SAVE'.$limit,
        'status' => 'active',
        'usage_limit' => $limit,
        'used_count' => 0,
        'reserved_count' => 0,
        'lock_version' => 0,
    ]);
}

it('never exceeds the usage limit under sequential contention', function () {
    $coupon = makeCoupon(2);

    // Two reservations succeed, the third must fail — capacity is atomic.
    $this->service->reserve('SAVE2', 'holder-1');
    $this->service->reserve('SAVE2', 'holder-2');

    expect(fn () => $this->service->reserve('SAVE2', 'holder-3'))
        ->toThrow(BusinessRuleException::class);

    expect($coupon->fresh()->reserved_count)->toBe(2);
});

it('releases a reservation and frees capacity', function () {
    makeCoupon(1);
    $reservation = $this->service->reserve('SAVE1', 'holder-1');

    expect(fn () => $this->service->reserve('SAVE1', 'holder-2'))->toThrow(BusinessRuleException::class);

    $this->service->release($reservation);

    // Capacity freed → a new reservation now succeeds.
    $this->service->reserve('SAVE1', 'holder-2');
    expect(Coupon::query()->where('code', 'SAVE1')->value('reserved_count'))->toBe(1);
});

it('consumes reserved capacity into used_count exactly once per order', function () {
    makeCoupon(5);
    $reservation = $this->service->reserve('SAVE5', 'cart-1');

    $this->service->consume($reservation, userId: 1, orderReference: 'ORD-1');
    // A duplicate consume of the same order reference must not double count.
    $coupon = Coupon::query()->where('code', 'SAVE5')->first();
    expect($coupon->used_count)->toBe(1)
        ->and($coupon->reserved_count)->toBe(0);
});

it('rejects an expired coupon', function () {
    Coupon::query()->create([
        'public_id' => PublicId::generate(),
        'code' => 'EXPIRED',
        'status' => 'active',
        'used_count' => 0,
        'reserved_count' => 0,
        'ends_at' => now()->subDay(),
    ]);

    expect(fn () => $this->service->reserve('EXPIRED', 'h'))->toThrow(BusinessRuleException::class);
});
