<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use App\Support\Clock\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Resolves an idempotency key for order placement (FR-CHK-008, BR-047, LLD §3.6).
 *
 * - First use of a key with a fingerprint → fresh (proceed).
 * - Replay of the same key + same fingerprint → replay (return stored response).
 * - Reuse of the key with a DIFFERENT fingerprint → conflict (reject, 409).
 *
 * The unique (idempotency_key, actor_key) index makes the first-insert race-safe.
 */
final class CheckoutIdempotencyService
{
    public function resolve(string $key, string $actorKey, string $fingerprint): IdempotencyResult
    {
        $existing = DB::table('checkout_idempotency_records')
            ->where('idempotency_key', $key)
            ->where('actor_key', $actorKey)
            ->first();

        if ($existing !== null) {
            if ($existing->request_fingerprint !== $fingerprint) {
                return IdempotencyResult::conflict();
            }
            if ($existing->status === 'completed' && $existing->response !== null) {
                /** @var array<string, mixed> $response */
                $response = json_decode($existing->response, true);

                return IdempotencyResult::replay($response);
            }

            return IdempotencyResult::fresh(); // in-progress; caller re-attempts within same fingerprint
        }

        // insertOrIgnore is race-safe: only one concurrent insert wins.
        $inserted = DB::table('checkout_idempotency_records')->insertOrIgnore([
            'idempotency_key' => $key,
            'actor_key' => $actorKey,
            'request_fingerprint' => $fingerprint,
            'status' => 'in_progress',
            'expires_at' => Clock::now()->addHours(24),
            'created_at' => Clock::now(),
            'updated_at' => Clock::now(),
        ]);

        if ($inserted === 0) {
            // Lost the race — re-resolve to get replay/conflict semantics.
            return $this->resolve($key, $actorKey, $fingerprint);
        }

        return IdempotencyResult::fresh();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function complete(string $key, string $actorKey, array $response): void
    {
        DB::table('checkout_idempotency_records')
            ->where('idempotency_key', $key)
            ->where('actor_key', $actorKey)
            ->update([
                'status' => 'completed',
                'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'updated_at' => Clock::now(),
            ]);
    }
}
