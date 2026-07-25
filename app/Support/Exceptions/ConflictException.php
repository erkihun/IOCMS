<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Throwable;

/** State or concurrency conflict (HTTP 409) — LLD §3.7. */
final class ConflictException extends DomainException
{
    /** @param array<string, string[]> $fields */
    public function __construct(string $errorCode, string $message = '', array $fields = [], ?Throwable $previous = null)
    {
        parent::__construct($errorCode, $message, 409, $fields, $previous);
    }
}
