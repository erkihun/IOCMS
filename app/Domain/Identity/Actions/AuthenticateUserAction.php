<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Identity\Data\AuthenticateUserData;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentifier;
use App\Domain\Identity\Services\IdentifierNormalizer;
use App\Support\Clock\Clock;
use App\Support\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticates a user by identifier + password (FR-IAM-002/009, BR-025).
 *
 * Account-enumeration resistance (FR-IAM-002, NFR-SE-020): a wrong identifier and
 * a wrong password both return the SAME generic `identity.invalid_credentials`
 * error, and a dummy hash is always verified so the response time does not reveal
 * whether the account exists. Suspended/terminated users cannot authenticate
 * (BR-025). Every attempt is recorded (hashed identifier) for throttling and
 * security evidence; throttling itself is enforced by the LoginRequest/route
 * middleware (FR-IAM-009). The password hash is never logged (NFR-SE-022).
 *
 * Returns the authenticated user. Session rotation happens in the controller
 * after this Action succeeds (NFR-SE-007).
 */
final class AuthenticateUserAction
{
    /** A constant bcrypt hash used to equalize timing for unknown accounts. */
    private const string DUMMY_HASH = '$2y$10$usesomesillystringforsalttoavoidtimingleakabcd1234567';

    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(AuthenticateUserData $data): User
    {
        $type = $this->normalizer->detectType($data->rawIdentifier);
        $normalized = $this->normalizer->normalize($type, $data->rawIdentifier);

        /** @var UserIdentifier|null $identifier */
        $identifier = UserIdentifier::query()
            ->where('type', $type->value)
            ->where('normalized_value', $normalized)
            ->first();

        $user = $identifier?->user;

        // Always perform a hash check (dummy when no account) to equalize timing
        // and avoid revealing account existence (FR-IAM-002).
        $hash = ($user !== null && $user->password_hash !== null)
            ? $user->password_hash
            : self::DUMMY_HASH;
        $passwordOk = Hash::check($data->password, $hash);

        $success = $user !== null
            && $passwordOk
            && $user->status->canAuthenticate();

        $this->recordAttempt($normalized, $data->ipAddress, $success ? 'success' : 'failed', $user, $success);

        if (! $success) {
            // Single generic error — never disclose which factor failed or whether
            // the account exists / is suspended (FR-IAM-002).
            throw new BusinessRuleException(
                'identity.invalid_credentials',
                'The identifier or password is incorrect.',
            );
        }

        $user->forceFill(['last_login_at' => Clock::now()])->save();

        return $user;
    }

    private function recordAttempt(string $normalized, ?string $ip, string $status, ?User $user, bool $success): void
    {
        DB::table('login_attempts')->insert([
            'identifier_hash' => hash('sha256', $normalized),
            'ip_address' => $ip,
            'status' => $status,
            'reason' => $success ? null : 'invalid_credentials',
            'created_at' => Clock::now(),
            'updated_at' => Clock::now(),
        ]);

        // Audit the outcome without leaking the identifier or password.
        $this->audit->record(
            action: $success ? 'identity.login_succeeded' : 'identity.login_failed',
            resourceType: 'user',
            resourcePublicId: $user?->public_id,
            outcome: $success ? 'success' : 'denied',
        );
    }
}
