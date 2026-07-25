<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Throwable;

/** Permission or record-scope denial (HTTP 403) — LLD §3.7, NFR-SE-012/013. */
final class AuthorizationException extends DomainException
{
    /** @param array<string, string[]> $fields */
    public function __construct(string $errorCode = 'authorization.out_of_scope', string $message = '', array $fields = [], ?Throwable $previous = null)
    {
        parent::__construct($errorCode, $message, 403, $fields, $previous);
    }
}
