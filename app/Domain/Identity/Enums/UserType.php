<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/** Actor type (IAM, LLD §5.1). Locale-neutral stable string values (no DB ENUM). */
enum UserType: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Seller = 'seller';
    case Machine = 'machine';

    public function isPrivileged(): bool
    {
        return $this === self::Staff;
    }
}
