<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Exceptions\ConflictException;
use Illuminate\Support\Facades\DB;

/**
 * Creates, suspends, reactivates or terminates a staff/seller account
 * (FR-IAM-010, BR-025). Authorization (permission + self-action guard) is
 * enforced by UserPolicy at the controller boundary; this Action enforces valid
 * status transitions, optimistic locking, and records audit + outbox with the
 * change in one transaction. Terminated is terminal.
 */
final class ChangeUserStatusAction
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(User $user, UserStatus $to, int $expectedLockVersion): User
    {
        return DB::transaction(function () use ($user, $to, $expectedLockVersion): User {
            /** @var User $fresh */
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($fresh->lock_version !== $expectedLockVersion) {
                throw new ConflictException('identity.concurrent_update', 'The account changed during processing.');
            }

            $from = $fresh->status;
            if ($from === $to) {
                return $fresh; // idempotent
            }
            if ($from === UserStatus::Terminated) {
                throw new BusinessRuleException('identity.status_transition_invalid', 'A terminated account cannot change status.');
            }

            $fresh->status = $to;
            $fresh->lock_version = $expectedLockVersion + 1;
            $fresh->save();

            $this->audit->record(
                action: 'identity.user_status_changed',
                resourceType: 'user',
                resourcePublicId: $fresh->public_id,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
            );

            $this->outbox->record(
                eventName: 'UserStatusChanged',
                aggregateType: 'user',
                aggregatePublicId: $fresh->public_id,
                payload: ['from' => $from->value, 'to' => $to->value],
                aggregateVersion: $fresh->lock_version,
            );

            return $fresh;
        });
    }
}
