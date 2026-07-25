<?php

declare(strict_types=1);

use App\Domain\Order\Models\OrderItem;
use App\Domain\Returns\Services\ReturnEligibilityService;
use App\Support\Clock\Clock;
use Carbon\CarbonImmutable;

/**
 * FR-RET-002, BR-012/038 — return eligibility boundaries (prompt §18).
 */
beforeEach(fn () => $this->service = new ReturnEligibilityService);

function orderItem(int $fulfilled, int $returned, ?int $windowDays): OrderItem
{
    $item = new OrderItem;
    $item->quantity_fulfilled = $fulfilled;
    $item->quantity_returned = $returned;
    $item->return_window_days = $windowDays;

    return $item;
}

afterEach(fn () => Clock::unfreeze());

it('allows a return within the window and remaining quantity', function () {
    $delivered = CarbonImmutable::create(2026, 7, 1, 0, 0, 0, 'UTC');
    Clock::freeze(CarbonImmutable::create(2026, 7, 10, 0, 0, 0, 'UTC')); // 9 days later
    $result = $this->service->evaluate(orderItem(fulfilled: 3, returned: 1, windowDays: 30), 2, $delivered);

    expect($result['eligible'])->toBeTrue()->and($result['max_quantity'])->toBe(2);
});

it('rejects when the requested quantity exceeds the remaining returnable (BR-012)', function () {
    $delivered = CarbonImmutable::create(2026, 7, 1, 0, 0, 0, 'UTC');
    Clock::freeze(CarbonImmutable::create(2026, 7, 5, 0, 0, 0, 'UTC'));
    $result = $this->service->evaluate(orderItem(fulfilled: 2, returned: 2, windowDays: 30), 1, $delivered);

    expect($result['eligible'])->toBeFalse()->and($result['reason'])->toBe('return.quantity_exceeded');
});

it('rejects when the return window has expired (BR-038 snapshotted window)', function () {
    $delivered = CarbonImmutable::create(2026, 6, 1, 0, 0, 0, 'UTC');
    Clock::freeze(CarbonImmutable::create(2026, 7, 10, 0, 0, 0, 'UTC')); // 39 days later
    $result = $this->service->evaluate(orderItem(fulfilled: 1, returned: 0, windowDays: 30), 1, $delivered);

    expect($result['eligible'])->toBeFalse()->and($result['reason'])->toBe('return.window_expired');
});

it('rejects a non-returnable item (no window)', function () {
    $result = $this->service->evaluate(orderItem(fulfilled: 1, returned: 0, windowDays: null), 1);
    expect($result['eligible'])->toBeFalse()->and($result['reason'])->toBe('return.ineligible');
});
