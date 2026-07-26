<?php

declare(strict_types=1);

namespace App\Http\Requests\IAM;

use App\Domain\Identity\Data\AuthenticateUserData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and normalizes sign-in input (FR-IAM-002). Business decisions
 * (credential check, enumeration resistance, throttling outcome) live in the
 * Action; this only validates transport shape (NFR-SE-004).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): AuthenticateUserData
    {
        return new AuthenticateUserData(
            rawIdentifier: (string) $this->string('identifier'),
            password: (string) $this->string('password'),
            ipAddress: $this->ip(),
            userAgent: (string) $this->userAgent(),
        );
    }
}
