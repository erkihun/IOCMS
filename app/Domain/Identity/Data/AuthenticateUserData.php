<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

/**
 * Validated input for authenticating a user (LLD §3.4 readonly DTO, FR-IAM-002).
 */
final readonly class AuthenticateUserData
{
    public function __construct(
        public string $rawIdentifier,
        public string $password,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
