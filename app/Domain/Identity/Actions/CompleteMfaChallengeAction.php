<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Enums\AuthenticatorStatus;
use App\Domain\Identity\Enums\AuthenticatorType;
use App\Domain\Identity\Models\Authenticator;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TotpService;
use App\Support\Clock\Clock;
use App\Support\Exceptions\BusinessRuleException;

/**
 * Verifies an MFA (TOTP) challenge for login or step-up (FR-IAM-005, NFR-SE-009/
 * 011). Returns silently on success (the controller marks the session
 * mfa_confirmed / reauth_confirmed_at); throws `identity.mfa_code_invalid` on a
 * bad code. Records the authenticator's last-used time and audits the outcome.
 * No secret or code is logged (NFR-SE-022).
 */
final class CompleteMfaChallengeAction
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(User $user, string $code): void
    {
        if (! $user->mfa_enrolled) {
            throw new BusinessRuleException('identity.mfa_not_enrolled', 'MFA is not enrolled for this account.');
        }

        /** @var Authenticator|null $authenticator */
        $authenticator = Authenticator::query()
            ->where('user_id', $user->id)
            ->where('type', AuthenticatorType::Totp->value)
            ->where('status', AuthenticatorStatus::Active->value)
            ->first();

        if ($authenticator === null || ! $this->totp->verify((string) $authenticator->secret, $code)) {
            $this->audit->record(
                action: 'identity.mfa_challenge_failed',
                resourceType: 'user',
                resourcePublicId: $user->public_id,
                outcome: 'denied',
            );

            throw new BusinessRuleException('identity.mfa_code_invalid', 'The verification code is incorrect.');
        }

        $authenticator->forceFill(['last_used_at' => Clock::now()])->save();

        $this->audit->record(
            action: 'identity.mfa_challenge_succeeded',
            resourceType: 'user',
            resourcePublicId: $user->public_id,
        );
    }
}
