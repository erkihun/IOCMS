<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserRole;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a role to a user at a record scope (FR-IAM-010/012, BR-026).
 *
 * Authorization (`roles.assign`) is enforced by RolePolicy at the boundary. The
 * assignment is idempotent (unique on user+role+scope). A seller/warehouse-scoped
 * role requires a scope id so the record scope is well-defined (BR-026). Audit +
 * RoleAssigned outbox commit with the change.
 */
final class AssignRoleAction
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(User $user, Role $role, ?string $scopeId, int $assignedByUserId): UserRole
    {
        // A non-global role must carry a concrete scope id (BR-026).
        if ($role->scope_type !== 'global' && ($scopeId === null || $scopeId === '')) {
            throw new BusinessRuleException(
                'authorization.out_of_scope',
                "A {$role->scope_type}-scoped role requires a scope reference.",
                ['scope_id' => ['This role requires a scope.']],
            );
        }

        return DB::transaction(function () use ($user, $role, $scopeId, $assignedByUserId): UserRole {
            /** @var UserRole $assignment */
            $assignment = UserRole::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'scope_type' => $role->scope_type,
                    'scope_id' => $scopeId,
                ],
                ['assigned_by' => $assignedByUserId],
            );

            if ($assignment->wasRecentlyCreated) {
                $this->audit->record(
                    action: 'identity.role_assigned',
                    resourceType: 'user',
                    resourcePublicId: $user->public_id,
                    after: ['role' => $role->code, 'scope_type' => $role->scope_type, 'scope_id' => $scopeId],
                );

                $this->outbox->record(
                    eventName: 'RoleAssigned',
                    aggregateType: 'user',
                    aggregatePublicId: $user->public_id,
                    payload: ['role' => $role->code, 'scope_type' => $role->scope_type, 'scope_id' => $scopeId],
                );
            }

            return $assignment;
        });
    }
}
