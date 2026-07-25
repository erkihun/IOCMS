<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Data;

use App\Support\Money\Money;

/**
 * A single priced line within a {@see PriceQuote}. All amounts are Money
 * (fixed precision, no float — BR-017).
 */
final readonly class LineQuote
{
    public function __construct(
        public string $variantPublicId,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineSubtotal,   // unitPrice * quantity
        public Money $discount,        // allocated discount for this line
        public Money $tax,
        public Money $lineTotal,       // subtotal - discount + tax
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'variant_public_id' => $this->variantPublicId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toDecimalString(),
            'line_subtotal' => $this->lineSubtotal->toDecimalString(),
            'discount' => $this->discount->toDecimalString(),
            'tax' => $this->tax->toDecimalString(),
            'line_total' => $this->lineTotal->toDecimalString(),
        ];
    }
}
