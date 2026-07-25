<?php

declare(strict_types=1);

use App\Support\Money\Money;

/**
 * Money value-object invariants (BR-017: fixed precision + documented rounding;
 * FR-LOC-006: no binary floating-point).
 */
it('constructs from a decimal string at scale 4', function () {
    $m = Money::fromDecimalString('1234.56', 'ETB');
    expect($m->minorUnits)->toBe(12345600)
        ->and($m->currency)->toBe('ETB')
        ->and($m->toDecimalString())->toBe('1234.5600');
});

it('adds and subtracts exactly with no float error', function () {
    $a = Money::fromDecimalString('0.10', 'ETB');
    $b = Money::fromDecimalString('0.20', 'ETB');
    // The classic 0.1 + 0.2 !== 0.3 float trap must not occur.
    expect($a->add($b)->toDecimalString())->toBe('0.3000');
});

it('rejects currency mismatch on arithmetic', function () {
    $a = Money::fromDecimalString('1.00', 'ETB');
    $b = Money::fromDecimalString('1.00', 'USD');
    expect(fn () => $a->add($b))->toThrow(InvalidArgumentException::class);
});

it('multiplies by an integer quantity exactly', function () {
    $unit = Money::fromDecimalString('19.99', 'ETB');
    expect($unit->multiplyByInt(3)->toDecimalString())->toBe('59.9700');
});

it('multiplies by a decimal rate with half-up rounding', function () {
    $base = Money::fromDecimalString('100.00', 'ETB');
    // 15% VAT → 15.0000
    expect($base->multiplyByRate('0.15')->toDecimalString())->toBe('15.0000');
    // half-up: 0.005 rounds up at scale 4 boundary
    $odd = Money::fromDecimalString('1.0001', 'ETB');
    expect($odd->multiplyByRate('0.5')->toDecimalString())->toBe('0.5001');
});

it('allocates an amount across weights with no rounding leakage', function () {
    $total = Money::fromDecimalString('10.00', 'ETB'); // 100000 minor
    $parts = $total->allocate([1, 1, 1]);
    $sum = array_reduce(
        $parts,
        fn (Money $carry, Money $p) => $carry->add($p),
        Money::zero('ETB')
    );
    expect($sum->equals($total))->toBeTrue()
        ->and(count($parts))->toBe(3);
});

it('rounds to currency presentation scale', function () {
    $m = Money::fromDecimalString('1.2399', 'ETB');
    expect($m->roundToCurrency(2)->toDecimalString())->toBe('1.2400');
});

it('never returns negative available via abs and compares correctly', function () {
    $a = Money::fromDecimalString('5.00', 'ETB');
    $b = Money::fromDecimalString('3.00', 'ETB');
    expect($a->greaterThan($b))->toBeTrue()
        ->and($b->subtract($a)->isNegative())->toBeTrue()
        ->and($b->subtract($a)->abs()->toDecimalString())->toBe('2.0000');
});
