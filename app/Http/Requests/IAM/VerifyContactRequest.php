<?php

declare(strict_types=1);

namespace App\Http\Requests\IAM;

use Illuminate\Foundation\Http\FormRequest;

/** Validates a contact-verification confirmation (FR-IAM-003). */
final class VerifyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:16', 'max:255'],
        ];
    }
}
