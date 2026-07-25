<?php

declare(strict_types=1);

namespace App\Domain\Localization\Enums;

/** Launch + configured currencies (FR-LOC-006 ETB launch currency). */
enum CurrencyCode: string
{
    case ETB = 'ETB';
    case USD = 'USD';
}
