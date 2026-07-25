<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/** Contact identifier kind used for registration/sign-in (FR-IAM-001). */
enum IdentifierType: string
{
    case Email = 'email';
    case Phone = 'phone';
}
