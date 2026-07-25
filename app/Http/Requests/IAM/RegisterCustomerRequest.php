<?php

declare(strict_types=1);

namespace App\Http\Requests\IAM;

use App\Domain\Identity\Data\RegisterCustomerData;
use App\Domain\Identity\Services\IdentifierNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and normalizes customer registration input (FR-IAM-001/004).
 * Validation lives here (transport), business rules in the Action. Frontend
 * validation never replaces this server-side check (NFR-SE-004).
 */
final class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guests may register; the Action + DB enforce uniqueness and policy.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'locale' => ['nullable', 'string', 'in:en,am'],
        ];
    }

    public function toData(IdentifierNormalizer $normalizer): RegisterCustomerData
    {
        $raw = (string) $this->string('identifier');

        return new RegisterCustomerData(
            identifierType: $normalizer->detectType($raw),
            rawIdentifier: $raw,
            password: (string) $this->string('password'),
            locale: (string) ($this->input('locale') ?? config('iocms.locales.default', 'en')),
            displayName: $this->input('display_name'),
        );
    }
}
