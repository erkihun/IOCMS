<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserSession;

/**
 * Authorizes session management (FR-IAM-008, NFR-SE-013 IDOR prevention).
 *
 * A user may only view or revoke their OWN sessions — ownership is the record
 * scope. This prevents insecure direct object reference: even with a valid
 * session public id, an actor cannot revoke another user's session.
 */
final class SessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // any authenticated user may list their own sessions
    }

    public function revoke(User $user, UserSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
