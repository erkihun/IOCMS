<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Data\LineQuote;
use App\Domain\Pricing\Data\PriceQuote;
use App\Support\Money\Money;

/**
 * Deterministic pricing calculator (FR-PRI-006/007, BR-014..018, NFR-FS-002).
 *
 * Given authoritative inputs (unit prices, quantities, an optional order-level
 * discount, a tax rate and a delivery amount) it produces a reproducible quote
 * with the discount allocated across lines by subtotal weight (largest-remainder,
 * no rounding leakage) and per-line tax computed on the net line amount. The
 * accounting identity (BR-018) is asserted before returning.
 */
final class PricingEngine
{
    /**
     * @param  array<int, array{variant_public_id: string, quantity: int, unit_price: Money}>  $lines
     */
    public function quote(
        array $lines,
        string $currency,
        ?Money $orderDiscount = null,
        string $taxRate = '0',
        ?Money $delivery = null,
    ): PriceQuote {
        $orderDiscount ??= Money::zero($currency);
        $delivery ??= Money::zero($currency);

        // 1) Line subtotals.
        $subtotals = [];
        $itemsSubtotal = Money::zero($currency);
        foreach ($lines as $i => $line) {
            $sub = $line['unit_price']->multiplyByInt($line['quantity']);
            $subtotals[$i] = $sub;
            $itemsSubtotal = $itemsSubtotal->add($sub);
        }

        // 2) Allocate the order discount across lines by subtotal weight so the
        //    parts sum exactly to the discount (BR-017 no leakage).
        $weights = array_map(fn (Money $m): int => $m->minorUnits, $subtotals);
        $discountParts = $itemsSubtotal->isZero() || $orderDiscount->isZero()
            ? array_fill(0, count($lines), Money::zero($currency))
            : $orderDiscount->allocate($weights === [] ? [1] : $weights);

        // 3) Per-line net, tax and total.
        $lineQuotes = [];
        $discountTotal = Money::zero($currency);
        $taxTotal = Money::zero($currency);
        $i = 0;
        foreach ($lines as $line) {
            $sub = $subtotals[$i];
            $discount = $discountParts[$i] ?? Money::zero($currency);
            $net = $sub->subtract($discount);
            $tax = $net->multiplyByRate($taxRate);

            $lineQuotes[] = new LineQuote(
                variantPublicId: $line['variant_public_id'],
                quantity: $line['quantity'],
                unitPrice: $line['unit_price'],
                lineSubtotal: $sub,
                discount: $discount,
                tax: $tax,
                lineTotal: $net->add($tax),
            );

            $discountTotal = $discountTotal->add($discount);
            $taxTotal = $taxTotal->add($tax);
            $i++;
        }

        $grandTotal = $itemsSubtotal
            ->subtract($discountTotal)
            ->add($taxTotal)
            ->add($delivery);

        $quote = new PriceQuote(
            lines: $lineQuotes,
            itemsSubtotal: $itemsSubtotal,
            discountTotal: $discountTotal,
            taxTotal: $taxTotal,
            deliveryTotal: $delivery,
            grandTotal: $grandTotal,
            currency: $currency,
        );

        assert($quote->isBalanced(), 'Price quote violates the BR-018 accounting identity.');

        return $quote;
    }
}
