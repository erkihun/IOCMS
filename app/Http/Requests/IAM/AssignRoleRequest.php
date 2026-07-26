<?php

declare(strict_types=1);

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

/** Validates a role assignment (FR-IAM-010/012). */
final class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is via RolePolicy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_code' => ['required', 'string', 'exists:roles,code'],
            'scope_id' => ['nullable', 'string', 'max:36'],
        ];
    }
}
