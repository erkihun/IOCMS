<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds locales, ETB currency and calendar display rules (prompt §22).
 * Development reference data only — no secrets.
 */
final class LocalizationSeeder extends Seeder
{
    public function run(): void
    {
        $locales = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'calendar_system' => 'gregorian', 'is_default' => true, 'sort_order' => 1],
            ['code' => 'am', 'name' => 'Amharic', 'native_name' => 'አማርኛ', 'calendar_system' => 'ethiopian', 'is_default' => false, 'sort_order' => 2],
        ];
        foreach ($locales as $l) {
            DB::table('locales')->updateOrInsert(['code' => $l['code']], [...$l, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('currency_configs')->updateOrInsert(
            ['code' => 'ETB'],
            ['symbol' => 'Br', 'display_scale' => 2, 'rounding_mode' => 'half_up', 'is_enabled' => true, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        foreach ([['am', 'ethiopian'], ['en', 'gregorian']] as [$locale, $cal]) {
            DB::table('calendar_display_rules')->updateOrInsert(
                ['locale' => $locale, 'calendar_system' => $cal],
                ['date_format' => 'yyyy-MM-dd', 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
