<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Domain\Governance\Models\AuditEvent;
use App\Support\Clock\Clock;
use App\Support\Correlation\CorrelationContext;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\Auth;

/**
 * Records tamper-evident audit evidence (FR-AUD-001/002/009, NFR-SE-021/022).
 *
 * Each event stores a SHA-256 hash chained from the previous event's hash, so
 * any modification or deletion of an intermediate row breaks the chain and is
 * detectable. Before/after values are redacted of secret keys (NFR-SE-022).
 * Recording happens inside the caller's transaction so audit and the audited
 * change commit atomically (FR-AUD-001).
 */
final class AuditRecorder
{
    private const array REDACT_KEYS = [
        'password', 'password_hash', 'secret', 'token', 'token_hash', 'cvv',
        'card_number', 'otp', 'private_key', 'api_key',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?string $resourceType = null,
        ?string $resourcePublicId = null,
        string $outcome = 'success',
        ?array $before = null,
        ?array $after = null,
    ): AuditEvent {
        $actor = Auth::user();
        $previousHash = AuditEvent::query()->orderByDesc('id')->value('hash');

        $actorType = 'system';
        if ($actor !== null) {
            $type = $actor->getAttribute('type');
            $actorType = $type instanceof \BackedEnum ? (string) $type->value : (string) ($type ?? 'staff');
        }

        $payload = [
            'actor_user_id' => $actor?->getAuthIdentifier(),
            'actor_type' => $actorType,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_public_id' => $resourcePublicId,
            'outcome' => $outcome,
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'ip_address' => request()?->ip(),
            'correlation_id' => CorrelationContext::id(),
            'previous_hash' => $previousHash,
        ];

        $hash = hash('sha256', ($previousHash ?? '').json_encode($payload, JSON_THROW_ON_ERROR));

        return AuditEvent::create([
            ...$payload,
            'public_id' => PublicId::generate(),
            'hash' => $hash,
            'created_at' => Clock::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (in_array(mb_strtolower((string) $key), self::REDACT_KEYS, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
