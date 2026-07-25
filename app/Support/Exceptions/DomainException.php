<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Correlation\CorrelationContext;
use RuntimeException;
use Throwable;

/**
 * Base class for expected business-rule failures.
 *
 * Implements the IOCMS error contract (LLD §3.7 / prompt §23): a stable,
 * locale-neutral error code, a localized human-readable message, a correlation
 * ID, optional field-level errors, and an HTTP status. Never exposes SQL, stack
 * traces, credentials, file paths, or infrastructure topology.
 */
class DomainException extends RuntimeException
{
    /**
     * @param  string  $errorCode  stable locale-neutral code, e.g. "checkout.inventory_unavailable"
     * @param  array<string, string[]>  $fields  field => localized messages
     */
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly int $status = 422,
        public readonly array $fields = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode, 0, $previous);
    }

    /**
     * The locale key used to resolve a safe, translated message (LLD §3.7).
     */
    public function translationKey(): string
    {
        return 'errors.'.$this->errorCode;
    }

    /**
     * @return array{error: array{code: string, message: string, correlation_id: string, fields: array<string, string[]>|\stdClass}}
     */
    public function toErrorArray(?string $localizedMessage = null): array
    {
        return [
            'error' => [
                'code' => $this->errorCode,
                'message' => $localizedMessage ?? $this->getMessage(),
                'correlation_id' => CorrelationContext::id(),
                // Empty fields serialize as {} not [] for a stable JSON contract.
                'fields' => $this->fields === [] ? new \stdClass : $this->fields,
            ],
        ];
    }
}
