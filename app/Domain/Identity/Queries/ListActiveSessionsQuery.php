<?php

declare(strict_types=1);

namespace App\Domain\Identity\Queries;

use App\Domain\Identity\Models\UserSession;
use Illuminate\Support\Collection;

/**
 * Read model: a user's active sessions (FR-IAM-008). Scope-safe by construction —
 * only the given user's sessions are returned. Builds a read model and never
 * mutates state (LLD §3.2).
 */
final class ListActiveSessionsQuery
{
    /**
     * @return Collection<int, UserSession>
     */
    public function forUser(int $userId): Collection
    {
        return UserSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get();
    }
}
