<?php

declare(strict_types=1);

namespace App\Support\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent cast binding a DECIMAL(20,4) amount column and its adjacent currency
 * column to a {@see Money} value object (LLD §3.5). Usage:
 *
 *   protected $casts = ['total' => MoneyCast::class.':total_currency'];
 *
 * @implements CastsAttributes<Money|null, Money|null>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly string $currencyColumn = 'currency') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $attributes[$this->currencyColumn] ?? 'ETB';

        return Money::fromDecimalString((string) $value, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! $value instanceof Money) {
            throw new \InvalidArgumentException('MoneyCast expects a Money instance.');
        }

        return [
            $key => $value->toDecimalString(),
            $this->currencyColumn => $value->currency,
        ];
    }
}
