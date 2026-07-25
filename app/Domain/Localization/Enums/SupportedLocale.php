<?php

declare(strict_types=1);

namespace App\Domain\Localization\Enums;

/** Supported UI locales (FR-LOC-001). */
enum SupportedLocale: string
{
    case English = 'en';
    case Amharic = 'am';

    public function calendarSystem(): CalendarSystem
    {
        return $this === self::Amharic ? CalendarSystem::Ethiopian : CalendarSystem::Gregorian;
    }
}
