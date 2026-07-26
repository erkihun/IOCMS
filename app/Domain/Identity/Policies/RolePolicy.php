<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;

/**
 * Authorizes role and permission management (FR-IAM-010, SDD ADR-016). Requires
 * the `roles.assign` permission and an active actor (BR-025).
 */
final class RolePolicy
{
    public function assign(User $actor): bool
    {
        return $actor->status === UserStatus::Active && $actor->hasPermission('roles.assign');
    }

    public function manage(User $actor): bool
    {
        return $actor->status === UserStatus::Active && $actor->hasPermission('users.manage');
    }
}
