<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Enums\AuthenticatorStatus;
use App\Domain\Identity\Enums\AuthenticatorType;
use App\Domain\Identity\Models\Authenticator;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TotpService;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Enrolls a TOTP authenticator for a user (FR-IAM-005, NFR-SE-009/015).
 *
 * Two phases:
 *  - begin(): generate a secret, store it on a PENDING authenticator (encrypted
 *    at rest), and return the provisioning URI for the authenticator app.
 *  - confirm(): verify a code produced from the pending secret, activate the
 *    authenticator, set the user's mfa_enrolled flag, and emit MfaEnrolled +
 *    audit in one transaction. A wrong code leaves enrollment pending.
 */
final class EnrollMfaAction
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @return array{authenticator_public_id: string, provisioning_uri: string}
     */
    public function begin(User $user): array
    {
        $secret = $this->totp->generateSecret();

        $authenticator = Authenticator::query()->create([
            'public_id' => PublicId::generate(),
            'user_id' => $user->id,
            'type' => AuthenticatorType::Totp->value,
            'status' => AuthenticatorStatus::Pending->value,
            'secret' => $secret, // encrypted cast (NFR-SE-015)
            'label' => 'Authenticator app',
        ]);

        $account = $user->email ?? $user->phone ?? $user->public_id;

        return [
            'authenticator_public_id' => $authenticator->public_id,
            'provisioning_uri' => $this->totp->provisioningUri($secret, $account, (string) config('app.name', 'IOCMS')),
        ];
    }

    public function confirm(User $user, string $authenticatorPublicId, string $code): void
    {
        DB::transaction(function () use ($user, $authenticatorPublicId, $code): void {
            /** @var Authenticator|null $authenticator */
            $authenticator = Authenticator::query()
                ->where('public_id', $authenticatorPublicId)
                ->where('user_id', $user->id)
                ->where('type', AuthenticatorType::Totp->value)
                ->lockForUpdate()
                ->first();

            if ($authenticator === null || $authenticator->status !== AuthenticatorStatus::Pending) {
                throw new BusinessRuleException('identity.mfa_enrollment_invalid', 'No pending MFA enrollment found.');
            }

            if (! $this->totp->verify((string) $authenticator->secret, $code)) {
                throw new BusinessRuleException('identity.mfa_code_invalid', 'The verification code is incorrect.');
            }

            $authenticator->forceFill(['status' => AuthenticatorStatus::Active->value])->save();
            User::query()->where('id', $user->id)->update(['mfa_enrolled' => true]);

            $this->audit->record(
                action: 'identity.mfa_enrolled',
                resourceType: 'user',
                resourcePublicId: $user->public_id,
            );

            $this->outbox->record(
                eventName: 'MfaEnrolled',
                aggregateType: 'user',
                aggregatePublicId: $user->public_id,
                payload: ['authenticator_public_id' => $authenticatorPublicId],
            );
        });
    }
}
