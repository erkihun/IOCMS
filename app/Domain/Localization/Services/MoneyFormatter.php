<?php

declare(strict_types=1);

namespace App\Domain\Localization\Services;

use App\Support\Money\Money;

/**
 * Locale-aware money presentation (FR-LOC-008). Rounds to the currency's display
 * scale for presentation only; the stored value keeps full DECIMAL(20,4) scale.
 */
final class MoneyFormatter
{
    /**
     * @var array<string, int>
     */
    private array $displayScale;

    public function __construct()
    {
        /** @var array<string, int> $scales */
        $scales = config('iocms.currency.display_scale', ['ETB' => 2]);
        $this->displayScale = $scales;
    }

    public function format(Money $money, string $locale = 'en'): string
    {
        $scale = $this->displayScale[$money->currency] ?? 2;
        $rounded = $money->roundToCurrency($scale);
        $decimal = $rounded->toDecimalString();

        // Trim to display scale.
        if ($scale < Money::SCALE) {
            $decimal = substr($decimal, 0, strpos($decimal, '.') + 1 + $scale);
        }

        $amount = number_format((float) $decimal, $scale, '.', ',');

        return $locale === 'am'
            ? $amount.' '.$money->currency
            : $money->currency.' '.$amount;
    }
}
