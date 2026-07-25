<?php

declare(strict_types=1);

use App\Domain\Localization\Services\EthiopianCalendarAdapter;
use App\Support\Exceptions\BusinessRuleException;
use Carbon\CarbonImmutable;

/**
 * FR-LOC-004 — Ethiopian calendar presentation. Presentation only: stored
 * instants are never altered (BR-045). Verified against known reference dates.
 */
beforeEach(function () {
    $this->adapter = new EthiopianCalendarAdapter;
});

it('converts Ethiopian New Year 2017 E.C. (2024-09-11 Gregorian)', function () {
    $g = CarbonImmutable::create(2024, 9, 11, 12, 0, 0, 'Africa/Addis_Ababa');
    $e = $this->adapter->fromGregorian($g);

    expect($e['year'])->toBe(2017)
        ->and($e['month'])->toBe(1)  // Mäskäräm
        ->and($e['day'])->toBe(1);
});

it('round-trips Gregorian → Ethiopian → Gregorian', function () {
    $g = CarbonImmutable::create(2026, 7, 25, 0, 0, 0, 'Africa/Addis_Ababa');
    $e = $this->adapter->fromGregorian($g);
    $back = $this->adapter->toGregorian($e['year'], $e['month'], $e['day']);

    expect($back->toDateString())->toBe($g->toDateString());
});

it('formats an Amharic date string with a Ge`ez month name', function () {
    $g = CarbonImmutable::create(2024, 9, 11, 12, 0, 0, 'Africa/Addis_Ababa');
    expect($this->adapter->format($g))->toContain('መስከረም');
});

it('rejects an invalid Ethiopian date', function () {
    expect(fn () => $this->adapter->toGregorian(2017, 14, 40))
        ->toThrow(BusinessRuleException::class);
});
