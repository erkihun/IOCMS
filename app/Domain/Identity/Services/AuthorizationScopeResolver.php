<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserRole;
use Illuminate\Support\Collection;

/**
 * Resolves a user's effective permissions and record scopes from currently
 * effective role assignments (FR-IAM-011/012, SDD ADR-016). Deny-by-default:
 * only permissions granted by an active, in-window role assignment are effective.
 */
final class AuthorizationScopeResolver
{
    /**
     * @return string[] permission codes the user currently holds
     */
    public function permissionCodesFor(User $user): array
    {
        $roleIds = $this->effectiveAssignments($user)->pluck('role_id')->unique()->all();

        if ($roleIds === []) {
            return [];
        }

        return Permission::query()
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('role_permissions.role_id', $roleIds)
            ->where('permissions.status', 'active')
            ->pluck('permissions.code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether the user's scope contains a target of the given scope type and id.
     * A global assignment satisfies any target of that dimension.
     */
    public function scopeContains(User $user, string $scopeType, ?string $scopeId): bool
    {
        return $this->effectiveAssignments($user)->contains(function (UserRole $r) use ($scopeType, $scopeId): bool {
            if ($r->scope_type === 'global') {
                return true;
            }

            return $r->scope_type === $scopeType
                && ($r->scope_id === null || $r->scope_id === $scopeId);
        });
    }

    /**
     * @return Collection<int, UserRole>
     */
    private function effectiveAssignments(User $user): Collection
    {
        /** @var Collection<int, UserRole> $assignments */
        $assignments = $user->roleAssignments()->get()
            ->filter(fn (UserRole $r): bool => $r->isCurrentlyEffective())
            ->values();

        return $assignments;
    }
}
