<?php

declare(strict_types=1);

namespace App\Http\Requests\IAM;

use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/** Validates a staff/seller account status change (FR-IAM-010). */
final class ChangeUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is via UserPolicy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', new Enum(UserStatus::class)],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function status(): UserStatus
    {
        return UserStatus::from((string) $this->string('status'));
    }
}
