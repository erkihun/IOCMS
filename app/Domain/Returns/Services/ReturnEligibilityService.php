<?php

declare(strict_types=1);

namespace App\Domain\Returns\Services;

use App\Domain\Order\Models\OrderItem;
use App\Support\Clock\Clock;
use Carbon\CarbonImmutable;

/**
 * Evaluates return eligibility for an order line (FR-RET-002, BR-012/038).
 *
 * Eligibility uses the return policy version and window snapshotted on the order
 * item at purchase (BR-038, unless law requires a more favorable rule), the
 * delivery date, and the remaining returnable quantity (delivered − prior
 * accepted returns, BR-012).
 */
final class ReturnEligibilityService
{
    /**
     * @return array{eligible: bool, reason: string|null, max_quantity: int}
     */
    public function evaluate(OrderItem $item, int $requestedQuantity, ?CarbonImmutable $deliveredAt = null): array
    {
        $remaining = $item->quantity_fulfilled - $item->quantity_returned;

        if ($item->return_window_days === null) {
            return ['eligible' => false, 'reason' => 'return.ineligible', 'max_quantity' => 0];
        }

        if ($remaining <= 0) {
            return ['eligible' => false, 'reason' => 'return.quantity_exceeded', 'max_quantity' => 0];
        }

        if ($requestedQuantity > $remaining) {
            return ['eligible' => false, 'reason' => 'return.quantity_exceeded', 'max_quantity' => $remaining];
        }

        // Window check against the snapshotted window and the delivery date.
        $anchor = $deliveredAt ?? Clock::now();
        $deadline = $anchor->addDays((int) $item->return_window_days);
        if (Clock::now()->greaterThan($deadline)) {
            return ['eligible' => false, 'reason' => 'return.window_expired', 'max_quantity' => $remaining];
        }

        return ['eligible' => true, 'reason' => null, 'max_quantity' => $remaining];
    }
}
