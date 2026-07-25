<?php

declare(strict_types=1);

namespace App\Domain\Localization\Enums;

/** Money rounding policy (BR-017 documented rounding). */
enum RoundingMode: string
{
    case HalfUp = 'half_up';
    case HalfEven = 'half_even';
    case Down = 'down';
}
