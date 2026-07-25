<?php

declare(strict_types=1);

namespace App\Support\Clock;

use Carbon\CarbonImmutable;

/**
 * Single source of "now", always in UTC (BR-045 / FR-LOC-005 / SDD ADR-015).
 *
 * Calendar localization (Ethiopian/Gregorian) is a presentation concern handled
 * by the Localization module; stored instants are always canonical UTC.
 * Tests may freeze time via {@see freeze()} for deterministic assertions.
 */
final class Clock
{
    private static ?CarbonImmutable $frozen = null;

    public static function now(): CarbonImmutable
    {
        return self::$frozen ?? CarbonImmutable::now('UTC');
    }

    public static function freeze(?CarbonImmutable $at = null): CarbonImmutable
    {
        return self::$frozen = ($at ?? CarbonImmutable::now('UTC'))->utc();
    }

    public static function unfreeze(): void
    {
        self::$frozen = null;
    }
}
