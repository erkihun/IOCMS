<?php

declare(strict_types=1);

use App\Domain\Pricing\Services\PricingEngine;
use App\Support\Money\Money;

/**
 * FR-PRI-006/007, BR-014..018 — deterministic pricing with balanced totals and
 * no rounding leakage. Test basis for T-FR-PRI-007.
 */
beforeEach(fn () => $this->engine = new PricingEngine);

function line(string $id, int $qty, string $price): array
{
    return ['variant_public_id' => $id, 'quantity' => $qty, 'unit_price' => Money::fromDecimalString($price, 'ETB')];
}

it('produces a balanced quote (BR-018 accounting identity)', function () {
    $quote = $this->engine->quote(
        lines: [line('v1', 2, '100.00'), line('v2', 1, '50.00')],
        currency: 'ETB',
        orderDiscount: Money::fromDecimalString('30.00', 'ETB'),
        taxRate: '0.15',
        delivery: Money::fromDecimalString('25.00', 'ETB'),
    );

    expect($quote->isBalanced())->toBeTrue()
        ->and($quote->itemsSubtotal->toDecimalString())->toBe('250.0000')
        ->and($quote->discountTotal->toDecimalString())->toBe('30.0000');
});

it('is deterministic for identical inputs (NFR-FS-002)', function () {
    $args = fn () => $this->engine->quote(
        lines: [line('v1', 3, '19.99'), line('v2', 2, '5.50')],
        currency: 'ETB',
        orderDiscount: Money::fromDecimalString('7.77', 'ETB'),
        taxRate: '0.15',
    );

    expect($args()->toArray())->toBe($args()->toArray());
});

it('allocates discount across lines with no rounding leakage', function () {
    // 10.00 discount across three equal lines cannot divide evenly at cents.
    $quote = $this->engine->quote(
        lines: [line('a', 1, '10.00'), line('b', 1, '10.00'), line('c', 1, '10.00')],
        currency: 'ETB',
        orderDiscount: Money::fromDecimalString('10.00', 'ETB'),
        taxRate: '0',
    );

    $sumDiscount = array_reduce(
        $quote->lines,
        fn (Money $c, $l) => $c->add($l->discount),
        Money::zero('ETB')
    );
    expect($sumDiscount->toDecimalString())->toBe('10.0000'); // exact, no leakage
});

it('computes tax on the net (post-discount) line amount', function () {
    $quote = $this->engine->quote(
        lines: [line('v1', 1, '100.00')],
        currency: 'ETB',
        orderDiscount: Money::fromDecimalString('20.00', 'ETB'),
        taxRate: '0.15',
    );
    // net 80.00 * 15% = 12.00
    expect($quote->taxTotal->toDecimalString())->toBe('12.0000')
        ->and($quote->grandTotal->toDecimalString())->toBe('92.0000');
});
