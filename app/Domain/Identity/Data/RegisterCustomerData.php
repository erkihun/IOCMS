<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Enums\IdentifierType;

/**
 * Validated input for registering a customer (LLD §3.4 readonly DTO).
 */
final readonly class RegisterCustomerData
{
    public function __construct(
        public IdentifierType $identifierType,
        public string $rawIdentifier,
        public string $password,
        public string $locale,
        public ?string $displayName = null,
    ) {}
}
