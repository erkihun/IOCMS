<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\IdentifierType;

/**
 * Normalizes contact identifiers so uniqueness (FR-IAM-001) is enforced on a
 * canonical form regardless of user formatting. Email is lower-cased and
 * trimmed; phone numbers are reduced to digits with an Ethiopian-aware E.164-ish
 * canonicalization (default country +251).
 */
final class IdentifierNormalizer
{
    public function normalize(IdentifierType $type, string $value): string
    {
        return match ($type) {
            IdentifierType::Email => $this->normalizeEmail($value),
            IdentifierType::Phone => $this->normalizePhone($value),
        };
    }

    /**
     * Detect whether a raw value looks like an email or a phone number.
     */
    public function detectType(string $value): IdentifierType
    {
        return str_contains($value, '@') ? IdentifierType::Email : IdentifierType::Phone;
    }

    private function normalizeEmail(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $value) ?? '';

        // 09XXXXXXXX -> +2519XXXXXXXX ; 2519... -> +2519... ; keep leading +.
        if (str_starts_with($digits, '+')) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+251'.substr($digits, 1);
        }
        if (str_starts_with($digits, '251')) {
            return '+'.$digits;
        }

        return '+251'.$digits;
    }
}
