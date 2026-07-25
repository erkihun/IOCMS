<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Fixed-precision monetary value object.
 *
 * Enforces IOCMS-SRS BR-017 (fixed precision + documented rounding), FR-LOC-006
 * (no binary floating-point), and LLD §3.5 (DECIMAL(20,4) + ISO currency).
 *
 * Internally the amount is stored as an integer number of "scaled minor units"
 * at {@see self::SCALE} (4) decimal places, so every arithmetic operation is
 * exact integer arithmetic — never a float. Rounding to the currency's own
 * scale is applied only at explicit contract boundaries via {@see roundToCurrency()}.
 */
final readonly class Money
{
    /** Canonical storage scale for money (LLD §3.5: DECIMAL(20,4)). */
    public const int SCALE = 4;

    /**
     * @param  int  $minorUnits  amount expressed in 10^-4 units of the currency
     * @param  string  $currency  ISO-4217 alphabetic code, upper-case
     */
    public function __construct(
        public int $minorUnits,
        public string $currency,
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid ISO-4217 currency code: {$currency}");
        }
    }

    /**
     * Build from a decimal string such as "1234.5600" (the DB DECIMAL representation).
     */
    public static function fromDecimalString(string $amount, string $currency): self
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid decimal money string: {$amount}");
        }

        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = substr(str_pad($fraction, self::SCALE, '0'), 0, self::SCALE);

        // Extra digits beyond SCALE are truncated deliberately; callers round first.
        $minor = (int) ($whole.$fraction);

        return new self($negative ? -$minor : $minor, strtoupper($currency));
    }

    /** Convenience constructor for a whole-plus-cents style amount at currency scale 2. */
    public static function ofMajor(int|float|string $major, string $currency): self
    {
        return self::fromDecimalString((string) $major, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * Multiply by an integer factor (e.g. quantity). Exact.
     */
    public function multiplyByInt(int $factor): self
    {
        return new self($this->minorUnits * $factor, $this->currency);
    }

    /**
     * Multiply by a decimal rate (e.g. a tax or discount rate) expressed as a
     * string like "0.150000". Uses half-up rounding to the storage scale — the
     * single documented rounding policy (BR-017).
     */
    public function multiplyByRate(string $rate): self
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $rate)) {
            throw new InvalidArgumentException("Invalid rate: {$rate}");
        }

        // Scale the rate to an integer, multiply, then divide back with half-up rounding.
        [$whole, $fraction] = array_pad(explode('.', ltrim($rate, '-'), 2), 2, '');
        $rateScale = strlen($fraction);
        $rateInt = (int) ($whole.$fraction);
        $negative = str_starts_with($rate, '-');

        $divisor = 10 ** $rateScale;
        $product = $this->minorUnits * ($negative ? -$rateInt : $rateInt);

        return new self(self::intDivHalfUp($product, $divisor), $this->currency);
    }

    /**
     * Allocate this amount across N weights with no rounding leakage (largest-remainder).
     * Guarantees the parts sum exactly to the original amount.
     *
     * @param  int[]  $weights
     * @return self[]
     */
    public function allocate(array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            throw new InvalidArgumentException('Allocation weights must sum to a positive value.');
        }

        $remainder = $this->minorUnits;
        $results = [];
        $shares = [];
        foreach ($weights as $i => $weight) {
            $share = intdiv($this->minorUnits * $weight, $total);
            $shares[$i] = $share;
            $remainder -= $share;
        }
        // Distribute the remaining minor units one-by-one to the largest fractional parts.
        $fractions = [];
        foreach ($weights as $i => $weight) {
            $fractions[$i] = ($this->minorUnits * $weight) % $total;
        }
        arsort($fractions);
        foreach (array_keys($fractions) as $i) {
            if ($remainder <= 0) {
                break;
            }
            $shares[$i]++;
            $remainder--;
        }
        ksort($shares);
        foreach ($shares as $share) {
            $results[] = new self($share, $this->currency);
        }

        return $results;
    }

    /**
     * Round the stored value to the given currency's presentation scale (default 2).
     * Returns a new Money still stored at SCALE but with lower-order digits zeroed.
     */
    public function roundToCurrency(int $currencyScale = 2): self
    {
        if ($currencyScale >= self::SCALE) {
            return $this;
        }
        $factor = 10 ** (self::SCALE - $currencyScale);

        return new self(self::intDivHalfUp($this->minorUnits, $factor) * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorUnits === $other->minorUnits;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits > $other->minorUnits;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits >= $other->minorUnits;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits < $other->minorUnits;
    }

    public function abs(): self
    {
        return new self(abs($this->minorUnits), $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minorUnits, $this->currency);
    }

    /**
     * Decimal string suitable for a DECIMAL(20,4) column, e.g. "1234.5600".
     */
    public function toDecimalString(): string
    {
        $negative = $this->minorUnits < 0;
        $abs = (string) abs($this->minorUnits);
        $abs = str_pad($abs, self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($abs, 0, -self::SCALE);
        $fraction = substr($abs, -self::SCALE);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    /**
     * @return array{minor_units: int, currency: string, decimal: string}
     */
    public function toArray(): array
    {
        return [
            'minor_units' => $this->minorUnits,
            'currency' => $this->currency,
            'decimal' => $this->toDecimalString(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }

    /**
     * Integer division with half-up rounding (away from zero at the .5 boundary),
     * the single documented rounding policy for IOCMS money.
     */
    private static function intDivHalfUp(int $numerator, int $divisor): int
    {
        $sign = ($numerator < 0) ? -1 : 1;
        $numerator = abs($numerator);
        $result = intdiv($numerator, $divisor);
        if (($numerator % $divisor) * 2 >= $divisor) {
            $result++;
        }

        return $sign * $result;
    }
}
