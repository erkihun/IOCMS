<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Models\UserSession;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Clock\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Revokes an active user session (FR-IAM-008). Idempotent — revoking an already
 * revoked session is a no-op. Ownership is authorized by SessionPolicy at the
 * controller boundary before this Action runs. Audit + SessionRevoked outbox
 * commit with the state change.
 */
final class RevokeSessionAction
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(UserSession $session): void
    {
        if (! $session->isActive()) {
            return; // idempotent
        }

        DB::transaction(function () use ($session): void {
            $session->forceFill(['revoked_at' => Clock::now()])->save();

            $this->audit->record(
                action: 'identity.session_revoked',
                resourceType: 'user_session',
                resourcePublicId: $session->public_id,
            );

            $this->outbox->record(
                eventName: 'SessionRevoked',
                aggregateType: 'user_session',
                aggregatePublicId: $session->public_id,
                payload: ['user_id' => $session->user_id],
            );
        });
    }
}
