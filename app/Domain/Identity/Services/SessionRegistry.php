<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\UserSession;
use App\Support\Clock\Clock;
use App\Support\Uuid\PublicId;

/**
 * Records and lists active user sessions for the FR-IAM-008 feature. The stored
 * session token is hashed (never the raw framework session id in plaintext).
 */
final class SessionRegistry
{
    public function record(int $userId, string $frameworkSessionId, ?string $ip, ?string $userAgent): UserSession
    {
        return UserSession::query()->create([
            'public_id' => PublicId::generate(),
            'user_id' => $userId,
            'session_token_hash' => hash('sha256', $frameworkSessionId),
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            'last_active_at' => Clock::now(),
        ]);
    }
}
