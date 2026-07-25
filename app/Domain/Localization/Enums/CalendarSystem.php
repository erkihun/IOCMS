<?php

declare(strict_types=1);

namespace App\Domain\Localization\Enums;

/** Calendar system for presentation (FR-LOC-004; presentation only — BR-045). */
enum CalendarSystem: string
{
    case Gregorian = 'gregorian';
    case Ethiopian = 'ethiopian';
}
