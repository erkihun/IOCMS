<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use App\Support\Uuid\PublicId;

/**
 * Holds the correlation identifier for the current request/job (BR-050, FR-AUD-009,
 * FR-INT-009, SDD ADR-019). Propagated across the browser request, HTTP request,
 * Action, DB transaction, outbox event, queue job, provider call and audit event.
 */
final class CorrelationContext
{
    private static ?string $id = null;

    public static function id(): string
    {
        return self::$id ??= PublicId::generate();
    }

    public static function set(string $id): void
    {
        self::$id = $id;
    }

    public static function reset(): void
    {
        self::$id = null;
    }
}
