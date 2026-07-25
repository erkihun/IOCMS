<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/** Authenticator lifecycle status (FR-IAM-005). */
enum AuthenticatorStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
}
