<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;

/**
 * Authorizes staff/seller user administration (FR-IAM-010/011, SDD ADR-016).
 *
 * The decision combines permission (`users.manage`) with a self-action guard:
 * an administrator may not change their own account status (a maker-checker-style
 * safety so an admin cannot lock themselves out or bypass separation of duties).
 * Only active actors may perform privileged actions (BR-025).
 */
final class UserPolicy
{
    public function changeStatus(User $actor, User $target): bool
    {
        if ($actor->status !== UserStatus::Active) {
            return false;
        }
        if ($actor->id === $target->id) {
            return false; // cannot change one's own status
        }

        return $actor->hasPermission('users.manage');
    }

    public function assignRole(User $actor, User $target): bool
    {
        if ($actor->status !== UserStatus::Active) {
            return false;
        }

        return $actor->hasPermission('roles.assign');
    }
}
