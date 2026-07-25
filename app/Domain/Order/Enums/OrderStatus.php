<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

/**
 * Order lifecycle (SRS §10.2.2, BR-003 independent state machine). Transitions
 * are guarded; direct status writes are prohibited (SDD §10.6).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return OrderStatus[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::PartiallyFulfilled, self::Fulfilled, self::Cancelled],
            self::PartiallyFulfilled => [self::Fulfilled, self::Cancelled],
            self::Fulfilled => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isCancellable(): bool
    {
        return ! in_array($this, [self::Delivered, self::Completed, self::Cancelled], true);
    }
}
