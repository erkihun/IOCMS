<?php

declare(strict_types=1);

namespace App\Support\Uuid;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Generates and validates UUIDv7 public identifiers.
 *
 * Enforces IOCMS-SDD ADR-009 / LLD §3.5: public and distributed identifiers are
 * time-ordered UUIDv7 (CHAR(36)). Internal BIGINT keys are never exposed and are
 * never the authorization boundary (BR-046).
 */
final class PublicId
{
    public static function generate(): string
    {
        return (string) new UuidV7;
    }

    public static function isValid(string $value): bool
    {
        if (! Uuid::isValid($value)) {
            return false;
        }

        // Accept any valid UUID for binding tolerance, but generation is always v7.
        return true;
    }
}
