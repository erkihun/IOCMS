<?php

declare(strict_types=1);

namespace App\Domain\Localization\Services;

use App\Support\Exceptions\BusinessRuleException;
use Carbon\CarbonImmutable;

/**
 * Converts between the (stored, canonical) Gregorian instant and the Ethiopian
 * calendar for presentation only (FR-LOC-004, BR-045, SDD ADR-015). Switching
 * locale never mutates stored timestamps — this adapter is presentation-side.
 *
 * Uses the standard JDN-based algorithm. The Ethiopian year has 13 months:
 * 12 months of 30 days + Pagumē (5 or 6 days in a leap year).
 */
final class EthiopianCalendarAdapter
{
    /** JDN offset for the Ethiopian epoch (1 Mäskäräm 1 E.C.). */
    private const int JD_EPOCH_OFFSET_AMETE_MIHRET = 1723856;

    private const array MONTH_NAMES = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ', 5 => 'ጥር',
        6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ', 9 => 'ግንቦት', 10 => 'ሰኔ',
        11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜ',
    ];

    /**
     * Convert a Gregorian instant (any timezone; date taken in the business tz)
     * to an Ethiopian date.
     *
     * @return array{year: int, month: int, day: int, month_name: string}
     */
    public function fromGregorian(CarbonImmutable $date, string $timezone = 'Africa/Addis_Ababa'): array
    {
        $local = $date->setTimezone($timezone);
        $jdn = $this->gregorianToJdn($local->year, $local->month, $local->day);

        $r = ($jdn - self::JD_EPOCH_OFFSET_AMETE_MIHRET) % 1461;
        $n = ($r % 365) + (365 * intdiv($r, 1460));

        $year = 4 * intdiv($jdn - self::JD_EPOCH_OFFSET_AMETE_MIHRET, 1461)
            + intdiv($r, 365) - intdiv($r, 1460);
        $month = intdiv($n, 30) + 1;
        $day = ($n % 30) + 1;

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'month_name' => self::MONTH_NAMES[$month] ?? '',
        ];
    }

    /**
     * Format an Ethiopian date as "day month_name year".
     */
    public function format(CarbonImmutable $date, string $timezone = 'Africa/Addis_Ababa'): string
    {
        $e = $this->fromGregorian($date, $timezone);

        return sprintf('%d %s %d', $e['day'], $e['month_name'], $e['year']);
    }

    /**
     * Convert an Ethiopian date back to a Gregorian CarbonImmutable at midnight
     * in the business timezone.
     */
    public function toGregorian(int $year, int $month, int $day, string $timezone = 'Africa/Addis_Ababa'): CarbonImmutable
    {
        if ($month < 1 || $month > 13 || $day < 1 || $day > 30) {
            throw new BusinessRuleException('calendar.conversion_failed', 'Invalid Ethiopian date.');
        }

        // Inverse of fromGregorian: the Ethiopian day-of-epoch is
        // 365*year + leaps + 30*(month-1) + (day-1), offset by the epoch JDN.
        $jdn = self::JD_EPOCH_OFFSET_AMETE_MIHRET
            + 365 * $year
            + intdiv($year, 4)
            + 30 * ($month - 1)
            + ($day - 1);

        [$gy, $gm, $gd] = $this->jdnToGregorian($jdn);

        return CarbonImmutable::create($gy, $gm, $gd, 0, 0, 0, $timezone)
            ?? throw new BusinessRuleException('calendar.conversion_failed', 'Date could not be represented.');
    }

    private function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day + intdiv(153 * $m + 2, 5) + 365 * $y
            + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return [$year, $month, $day];
    }
}
