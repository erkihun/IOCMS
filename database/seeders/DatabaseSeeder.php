<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrates all reference and development seeders (prompt §22). All values
 * are obviously synthetic; no real credentials, PII, or production secrets.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            LocalizationSeeder::class,
            SystemSettingsSeeder::class,
            FeatureFlagSeeder::class,
            SuperAdminSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
