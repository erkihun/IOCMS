<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Uuid\PublicId;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds feature flags (prompt §22). The marketplace master gate is seeded OFF
 * and stays off until governance readiness (SDD ADR-020, decision D-005).
 */
final class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            ['key' => 'marketplace', 'name' => 'Marketplace phase', 'is_enabled' => false],
            ['key' => 'recommendations', 'name' => 'Personalized recommendations', 'is_enabled' => false],
            ['key' => 'loyalty', 'name' => 'Loyalty points', 'is_enabled' => false],
        ];

        foreach ($flags as $f) {
            DB::table('feature_flags')->updateOrInsert(
                ['key' => $f['key']],
                [
                    'public_id' => PublicId::generate(),
                    'name' => $f['name'],
                    'is_enabled' => $f['is_enabled'],
                    'status' => 'active',
                    'owner' => 'platform',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
