<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Throwable;

/** Resource not found / existence not safe to reveal (HTTP 404) — LLD §3.7. */
final class NotFoundException extends DomainException
{
    /** @param array<string, string[]> $fields */
    public function __construct(string $errorCode, string $message = '', array $fields = [], ?Throwable $previous = null)
    {
        parent::__construct($errorCode, $message, 404, $fields, $previous);
    }
}
