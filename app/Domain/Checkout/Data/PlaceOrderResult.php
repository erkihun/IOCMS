<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Data;

/**
 * Stable, public result of order placement returned to the caller and stored
 * for idempotent replay (FR-CHK-008). Exposes only public references.
 */
final readonly class PlaceOrderResult
{
    public function __construct(
        public string $orderPublicId,
        public string $orderNumber,
        public string $status,
        public string $grandTotal,
        public string $currency,
        public bool $replayed = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order_public_id' => $this->orderPublicId,
            'order_number' => $this->orderNumber,
            'status' => $this->status,
            'grand_total' => $this->grandTotal,
            'currency' => $this->currency,
            'replayed' => $this->replayed,
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    public static function fromStored(array $stored): self
    {
        return new self(
            orderPublicId: (string) $stored['order_public_id'],
            orderNumber: (string) $stored['order_number'],
            status: (string) $stored['status'],
            grandTotal: (string) $stored['grand_total'],
            currency: (string) $stored['currency'],
            replayed: true,
        );
    }
}
