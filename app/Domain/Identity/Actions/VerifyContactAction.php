<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Models\ContactVerification;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Clock\Clock;
use App\Support\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a contact channel using a time-limited, single-use token
 * (FR-IAM-003). The raw token is never stored — only its SHA-256 hash — so the
 * inbound token is hashed and matched. A token that is expired OR already
 * consumed yields `identity.verification_expired` (no replay). On success the
 * verification is marked used, the user's channel verified_at is set, and a
 * ContactVerified outbox event + audit evidence commit in one transaction.
 */
final class VerifyContactAction
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);

        DB::transaction(function () use ($tokenHash): void {
            /** @var ContactVerification|null $verification */
            $verification = ContactVerification::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            // Same generic error for missing / expired / already-used, so a
            // replayed or guessed token reveals nothing (FR-IAM-003).
            if ($verification === null || ! $verification->isConsumable()) {
                throw new BusinessRuleException(
                    'identity.verification_expired',
                    'The verification link is invalid, expired or already used.',
                );
            }

            $verification->forceFill([
                'status' => 'used',
                'consumed_at' => Clock::now(),
            ])->save();

            $column = $verification->channel === 'phone' ? 'phone_verified_at' : 'email_verified_at';
            DB::table('users')->where('id', $verification->user_id)->update([
                $column => Clock::now(),
                'status' => DB::raw("CASE WHEN status = 'pending' THEN 'active' ELSE status END"),
                'updated_at' => Clock::now(),
            ]);

            $userPublicId = DB::table('users')->where('id', $verification->user_id)->value('public_id');

            $this->audit->record(
                action: 'identity.contact_verified',
                resourceType: 'user',
                resourcePublicId: $userPublicId,
                after: ['channel' => $verification->channel],
            );

            $this->outbox->record(
                eventName: 'ContactVerified',
                aggregateType: 'user',
                aggregatePublicId: $userPublicId,
                payload: ['user_public_id' => $userPublicId, 'channel' => $verification->channel],
            );
        });
    }
}
