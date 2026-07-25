<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

/**
 * Outcome of resolving an idempotency key (LLD §3.6, FR-CHK-008, FR-INT-006).
 * A replayed identical request returns the stored result; a reused key with a
 * different request fingerprint is a conflict and must be rejected.
 */
final readonly class IdempotencyResult
{
    /**
     * @param  array<string, mixed>|null  $storedResponse
     */
    private function __construct(
        public bool $isReplay,
        public bool $isConflict,
        public ?array $storedResponse = null,
    ) {}

    public static function fresh(): self
    {
        return new self(isReplay: false, isConflict: false);
    }

    /**
     * @param  array<string, mixed>  $storedResponse
     */
    public static function replay(array $storedResponse): self
    {
        return new self(isReplay: true, isConflict: false, storedResponse: $storedResponse);
    }

    public static function conflict(): self
    {
        return new self(isReplay: false, isConflict: true);
    }
}
