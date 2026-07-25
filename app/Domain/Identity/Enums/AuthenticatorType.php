<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/** Authenticator kind (FR-IAM-005; phishing-resistant WebAuthn ready — NFR-SE-010). */
enum AuthenticatorType: string
{
    case Password = 'password';
    case Totp = 'totp';
    case Webauthn = 'webauthn';
    case RecoveryCode = 'recovery_code';
}
