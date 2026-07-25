<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Enums;

/** Shipment lifecycle (SRS §10.2.4, BR-003). */
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case FailedAttempt = 'failed_attempt';
    case ReturnToOrigin = 'return_to_origin';

    /** @return ShipmentStatus[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Ready, self::ReturnToOrigin],
            self::Ready => [self::Dispatched, self::ReturnToOrigin],
            self::Dispatched => [self::InTransit, self::ReturnToOrigin],
            self::InTransit => [self::OutForDelivery, self::ReturnToOrigin],
            self::OutForDelivery => [self::Delivered, self::FailedAttempt, self::ReturnToOrigin],
            self::FailedAttempt => [self::OutForDelivery, self::ReturnToOrigin],
            self::Delivered, self::ReturnToOrigin => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return $this === $to || in_array($to, $this->allowedTransitions(), true);
    }
}
