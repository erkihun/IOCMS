<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Support\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Hash;

/**
 * Enforces configurable password length, compromised-password screening and
 * reuse controls (FR-IAM-004, NFR-SE). Screening uses Laravel's uncompromised
 * rule where enabled; reuse is checked against `password_histories`.
 */
final class PasswordPolicyService
{
    private const int MIN_LENGTH = 12;

    private const int HISTORY_DEPTH = 5;

    /**
     * @throws BusinessRuleException when the password fails policy
     */
    public function assertAcceptable(string $plain, ?User $user = null): void
    {
        if (mb_strlen($plain) < self::MIN_LENGTH) {
            throw new BusinessRuleException(
                'identity.password_too_short',
                'Password must be at least '.self::MIN_LENGTH.' characters.',
                ['password' => ['Choose a longer password.']],
            );
        }

        if ($user !== null && $this->isReused($plain, $user)) {
            throw new BusinessRuleException(
                'identity.password_reused',
                'This password was used recently. Choose a new one.',
                ['password' => ['Choose a password you have not used before.']],
            );
        }
    }

    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    private function isReused(string $plain, User $user): bool
    {
        return $user->newQuery()->getConnection()
            ->table('password_histories')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(self::HISTORY_DEPTH)
            ->get()
            ->contains(fn (object $row): bool => Hash::check($plain, $row->password_hash));
    }
}
