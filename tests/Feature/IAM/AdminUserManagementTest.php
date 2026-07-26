<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserRole;
use App\Platform\Integration\Models\OutboxEvent;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-IAM-010/011/012 — staff/seller account status + role assignment with
 * permission + scope + self-action guards. Includes authorization-negative and
 * scope tests (prompt §6/§11).
 */
function makeStaff(array $permissions = []): User
{
    $user = User::query()->create([
        'public_id' => PublicId::generate(), 'type' => 'staff', 'status' => 'active',
        'email' => 'staff'.random_int(1, 99999).'@example.com', 'locale' => 'en',
        'password_hash' => bcrypt('x'), 'email_verified_at' => now(),
    ]);

    if ($permissions !== []) {
        $role = Role::query()->create(['code' => 'r-'.$user->id, 'name' => 'R', 'scope_type' => 'global', 'status' => 'active', 'system_owned' => false]);
        foreach ($permissions as $code) {
            $perm = Permission::query()->firstOrCreate(['code' => $code], ['resource' => explode('.', $code)[0], 'action' => 'manage', 'status' => 'active']);
            DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $perm->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        UserRole::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_id' => null, 'assigned_by' => $user->id]);
    }

    return $user->fresh();
}

it('lets an admin with users.manage suspend another account (audit + outbox)', function () {
    $admin = makeStaff(['users.manage']);
    $target = makeStaff();

    $this->actingAs($admin)
        ->put("/admin/users/{$target->public_id}/status", ['status' => 'suspended', 'lock_version' => 0])
        ->assertRedirect();

    expect($target->fresh()->status->value)->toBe('suspended');
    expect(OutboxEvent::query()->where('event_name', 'UserStatusChanged')->exists())->toBeTrue();
    expect(DB::table('audit_events')->where('action', 'identity.user_status_changed')->exists())->toBeTrue();
});

it('forbids an actor WITHOUT users.manage from changing status (403)', function () {
    $actor = makeStaff(); // no permission
    $target = makeStaff();

    $this->actingAs($actor)
        ->put("/admin/users/{$target->public_id}/status", ['status' => 'suspended', 'lock_version' => 0])
        ->assertForbidden();

    expect($target->fresh()->status->value)->toBe('active');
});

it('forbids an admin from changing their OWN status (self-action guard)', function () {
    $admin = makeStaff(['users.manage']);

    $this->actingAs($admin)
        ->put("/admin/users/{$admin->public_id}/status", ['status' => 'suspended', 'lock_version' => 0])
        ->assertForbidden();

    expect($admin->fresh()->status->value)->toBe('active');
});

it('rejects a stale lock_version with a conflict', function () {
    $admin = makeStaff(['users.manage']);
    $target = makeStaff();

    $this->actingAs($admin)
        ->put("/admin/users/{$target->public_id}/status", ['status' => 'suspended', 'lock_version' => 99])
        ->assertStatus(409); // concurrency conflict
});

it('assigns a role with roles.assign permission and emits RoleAssigned', function () {
    $admin = makeStaff(['roles.assign']);
    $target = makeStaff();
    Role::query()->create(['code' => 'support_agent', 'name' => 'Support', 'scope_type' => 'global', 'status' => 'active', 'system_owned' => true]);

    $this->actingAs($admin)
        ->post("/admin/users/{$target->public_id}/roles", ['role_code' => 'support_agent'])
        ->assertRedirect();

    expect(UserRole::query()->where('user_id', $target->id)->whereHas('role', fn ($q) => $q->where('code', 'support_agent'))->exists())->toBeTrue();
    expect(OutboxEvent::query()->where('event_name', 'RoleAssigned')->exists())->toBeTrue();
});

it('forbids role assignment without roles.assign (403)', function () {
    $actor = makeStaff(['users.manage']); // has users.manage but NOT roles.assign
    $target = makeStaff();
    Role::query()->create(['code' => 'auditor', 'name' => 'Auditor', 'scope_type' => 'global', 'status' => 'active', 'system_owned' => true]);

    $this->actingAs($actor)
        ->post("/admin/users/{$target->public_id}/roles", ['role_code' => 'auditor'])
        ->assertForbidden();
});

it('rejects a scoped role assignment without a scope reference (BR-026)', function () {
    $admin = makeStaff(['roles.assign']);
    $target = makeStaff();
    Role::query()->create(['code' => 'warehouse_operator', 'name' => 'WH', 'scope_type' => 'warehouse', 'status' => 'active', 'system_owned' => true]);

    $this->actingAs($admin)
        ->from('/')
        ->post("/admin/users/{$target->public_id}/roles", ['role_code' => 'warehouse_operator']) // no scope_id
        ->assertSessionHasErrors();
});
