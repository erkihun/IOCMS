<?php

declare(strict_types=1);

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

/** Validates an MFA challenge submission (FR-IAM-005). */
final class CompleteMfaChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
            'authenticator_public_id' => ['sometimes', 'string', 'max:36'],
        ];
    }
}
