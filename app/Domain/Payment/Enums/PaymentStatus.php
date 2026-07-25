<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

/**
 * Payment lifecycle (SRS §10.2.3, BR-003). A browser redirect never advances
 * this (BR-004); only authenticated provider evidence does (FR-PAY-005).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Disputed = 'disputed';

    /** @return PaymentStatus[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Authorized, self::Captured, self::Failed, self::Cancelled],
            self::Authorized => [self::Captured, self::Cancelled, self::Failed],
            self::Captured => [self::PartiallyRefunded, self::Refunded, self::Disputed],
            self::PartiallyRefunded => [self::Refunded, self::Disputed],
            self::Failed, self::Cancelled, self::Refunded, self::Disputed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return $this === $to || in_array($to, $this->allowedTransitions(), true);
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Captured, self::PartiallyRefunded, self::Refunded], true);
    }
}
