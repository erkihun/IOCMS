<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Uuid\PublicId;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds initial system settings (prompt §22). Commercial/legal values are
 * DEMO defaults, clearly non-production (decision D-008); real values require a
 * configuration + legal task. No secrets are stored here.
 */
final class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'commerce.reservation_ttl_minutes', 'type' => 'int', 'value' => '30', 'category' => 'commerce'],
            ['key' => 'commerce.default_return_window_days', 'type' => 'int', 'value' => '30', 'category' => 'commerce', 'is_significant' => true],
            ['key' => 'tax.default_class', 'type' => 'string', 'value' => 'standard', 'category' => 'tax', 'is_significant' => true],
            ['key' => 'checkout.guest_enabled', 'type' => 'bool', 'value' => '1', 'category' => 'checkout'],
            ['key' => 'payment.cod_enabled', 'type' => 'bool', 'value' => '1', 'category' => 'payment'],
            ['key' => 'payment.online_enabled', 'type' => 'bool', 'value' => '0', 'category' => 'payment'],
        ];

        foreach ($settings as $s) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $s['key']],
                [
                    'public_id' => PublicId::generate(),
                    'type' => $s['type'],
                    'value' => $s['value'],
                    'category' => $s['category'],
                    'status' => 'active',
                    'is_significant' => $s['is_significant'] ?? false,
                    'is_secret_ref' => false,
                    'current_version' => 1,
                    'lock_version' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
