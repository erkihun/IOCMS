<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Data;

use App\Support\Money\Money;

/**
 * A complete, reproducible price quote (BR-018: order total = Σ line totals +
 * tax + delivery + charges − allocated discounts). Deterministic for identical
 * governed inputs (NFR-FS-002).
 */
final readonly class PriceQuote
{
    /**
     * @param  LineQuote[]  $lines
     */
    public function __construct(
        public array $lines,
        public Money $itemsSubtotal,
        public Money $discountTotal,
        public Money $taxTotal,
        public Money $deliveryTotal,
        public Money $grandTotal,
        public string $currency,
    ) {}

    /**
     * Verify the accounting identity holds (defensive invariant check).
     */
    public function isBalanced(): bool
    {
        $expected = $this->itemsSubtotal
            ->subtract($this->discountTotal)
            ->add($this->taxTotal)
            ->add($this->deliveryTotal);

        return $expected->equals($this->grandTotal);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (LineQuote $l): array => $l->toArray(), $this->lines),
            'items_subtotal' => $this->itemsSubtotal->toDecimalString(),
            'discount_total' => $this->discountTotal->toDecimalString(),
            'tax_total' => $this->taxTotal->toDecimalString(),
            'delivery_total' => $this->deliveryTotal->toDecimalString(),
            'grand_total' => $this->grandTotal->toDecimalString(),
            'currency' => $this->currency,
        ];
    }
}
