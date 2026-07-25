<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Data\RegisterCustomerData;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Enums\UserType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentifier;
use App\Domain\Identity\Services\IdentifierNormalizer;
use App\Domain\Identity\Services\PasswordPolicyService;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Clock\Clock;
use App\Support\Exceptions\ConflictException;
use App\Support\Uuid\PublicId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Registers a customer with a verified-capable identifier (FR-IAM-001/004).
 *
 * Concurrency & integrity (prompt §12): the normalized identifier uniqueness is
 * enforced by a DB unique index, so two concurrent registrations for the same
 * identifier cannot both succeed — the loser receives `identity.identifier_taken`
 * (409). The user row, identifier, password history, audit event and outbox
 * event all commit in one transaction.
 */
final class RegisterCustomerAction
{
    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly PasswordPolicyService $passwords,
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(RegisterCustomerData $data): User
    {
        $this->passwords->assertAcceptable($data->password);

        $normalized = $this->normalizer->normalize($data->identifierType, $data->rawIdentifier);
        $hash = $this->passwords->hash($data->password);

        try {
            return DB::transaction(function () use ($data, $normalized, $hash): User {
                $user = User::create([
                    'public_id' => PublicId::generate(),
                    'type' => UserType::Customer->value,
                    'status' => UserStatus::Pending->value,
                    'display_name' => $data->displayName,
                    'email' => $data->identifierType->value === 'email' ? $normalized : null,
                    'phone' => $data->identifierType->value === 'phone' ? $normalized : null,
                    'locale' => $data->locale,
                    'password_hash' => $hash,
                ]);

                UserIdentifier::create([
                    'user_id' => $user->id,
                    'type' => $data->identifierType->value,
                    'normalized_value' => $normalized,
                    'display_value' => $data->rawIdentifier,
                    'is_primary' => true,
                ]);

                DB::table('password_histories')->insert([
                    'user_id' => $user->id,
                    'password_hash' => $hash,
                    'created_at' => Clock::now(),
                    'updated_at' => Clock::now(),
                ]);

                $this->audit->record(
                    action: 'identity.customer_registered',
                    resourceType: 'user',
                    resourcePublicId: $user->public_id,
                    outcome: 'success',
                );

                $this->outbox->record(
                    eventName: 'UserRegistered',
                    aggregateType: 'user',
                    aggregatePublicId: $user->public_id,
                    payload: ['user_public_id' => $user->public_id, 'identifier_type' => $data->identifierType->value],
                );

                return $user;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new ConflictException(
                    'identity.identifier_taken',
                    'That contact is already registered.',
                    ['identifier' => ['This contact is already in use.']],
                    $e,
                );
            }
            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // 23000/23505 SQLSTATE class covers unique/integrity constraint violations
        // across MySQL and SQLite.
        return in_array($e->getCode(), ['23000', '23505'], true)
            || str_contains(mb_strtolower($e->getMessage()), 'unique');
    }
}
