<?php

declare(strict_types=1);

namespace App\Domain\Administration\Services;

use App\Support\Exceptions\ConflictException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Generates governed human-readable references — order, shipment, return, case,
 * payout, batch numbers (FR-CHK-013, ADR-009, decision D-010). The number is a
 * display reference, never an authorization boundary (BR-046).
 *
 * Allocation is concurrency-safe via an atomic conditional update on
 * numbering_rules.next_value guarded by lock_version (retry on contention).
 */
final class BusinessReferenceGenerator
{
    public function next(string $key): string
    {
        $rule = DB::table('numbering_rules')->where('key', $key)->first();

        if ($rule === null) {
            // Lazily create a default rule (prefix from key).
            DB::table('numbering_rules')->insertOrIgnore([
                'public_id' => PublicId::generate(),
                'key' => $key,
                'prefix' => strtoupper(substr($key, 0, 3)),
                'next_value' => 1,
                'padding' => 6,
                'lock_version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $rule = DB::table('numbering_rules')->where('key', $key)->first();
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $current = (int) $rule->next_value;
            $affected = DB::table('numbering_rules')
                ->where('id', $rule->id)
                ->where('lock_version', $rule->lock_version)
                ->update([
                    'next_value' => $current + 1,
                    'lock_version' => $rule->lock_version + 1,
                    'updated_at' => now(),
                ]);

            if ($affected === 1) {
                return sprintf('%s-%s', $rule->prefix, str_pad((string) $current, (int) $rule->padding, '0', STR_PAD_LEFT));
            }

            $rule = DB::table('numbering_rules')->where('id', $rule->id)->first();
        }

        throw new ConflictException('numbering.sequence_conflict', 'Reference sequence could not be allocated safely.');
    }
}
