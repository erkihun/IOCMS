<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/** User lifecycle status (IAM, LLD §5.1; BR-025 suspended cannot start new tx). */
enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }

    public function canStartProtectedTransaction(): bool
    {
        return $this === self::Active;
    }
}
