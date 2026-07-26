<?php

declare(strict_types=1);

namespace App\Http\Controllers\IAM;

use App\Domain\Identity\Actions\AssignRoleAction;
use App\Domain\Identity\Actions\ChangeUserStatusAction;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\IAM\AssignRoleRequest;
use App\Http\Requests\IAM\ChangeUserStatusRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Staff/seller account administration transport (FR-IAM-010, admin channel).
 * Authorization (`users.manage` / `roles.assign` + self-action guard) is enforced
 * by UserPolicy/RolePolicy; the Actions perform the transitions with audit +
 * outbox. Route-model binding resolves users on the public id (BR-046).
 */
final class AdminUserController extends Controller
{
    public function changeStatus(
        ChangeUserStatusRequest $request,
        User $user,
        ChangeUserStatusAction $action,
    ): RedirectResponse {
        $this->authorize('changeStatus', $user);

        $action->execute($user, $request->status(), (int) $request->integer('lock_version'));

        return back()->with('success', __('Account status updated.'));
    }

    public function assignRole(
        AssignRoleRequest $request,
        User $user,
        AssignRoleAction $action,
    ): RedirectResponse {
        $this->authorize('assignRole', $user);

        $role = Role::query()->where('code', $request->string('role_code'))->firstOrFail();

        $action->execute($user, $role, $request->input('scope_id'), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', __('Role assigned.'));
    }
}
