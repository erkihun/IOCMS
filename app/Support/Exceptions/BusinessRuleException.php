<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Throwable;

/** Recognized business-rule / validation failure (HTTP 422) — LLD §3.7. */
final class BusinessRuleException extends DomainException
{
    /** @param array<string, string[]> $fields */
    public function __construct(string $errorCode, string $message = '', array $fields = [], ?Throwable $previous = null)
    {
        parent::__construct($errorCode, $message, 422, $fields, $previous);
    }
}
