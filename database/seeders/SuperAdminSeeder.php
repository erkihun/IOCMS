<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Enums\UserType;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentifier;
use App\Domain\Identity\Models\UserRole;
use App\Support\Uuid\PublicId;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds an obviously-synthetic initial Super Administrator for development
 * (prompt §22). NEVER a real credential: the password is a well-known dev value
 * and this seeder is not run in production seeding paths.
 */
final class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return; // never seed a default admin in production
        }

        $email = 'admin@iocms.local';

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'public_id' => PublicId::generate(),
                'type' => UserType::Staff->value,
                'status' => UserStatus::Active->value,
                'display_name' => 'Dev Super Admin (SYNTHETIC)',
                'locale' => 'en',
                'password_hash' => Hash::make('password-dev-only-change-me'),
                'email_verified_at' => now(),
                'mfa_enrolled' => false,
            ],
        );

        UserIdentifier::query()->firstOrCreate(
            ['type' => 'email', 'normalized_value' => $email],
            ['user_id' => $user->id, 'display_value' => $email, 'is_primary' => true, 'verified_at' => now()],
        );

        $role = Role::query()->where('code', 'super_admin')->first();
        if ($role) {
            UserRole::query()->firstOrCreate(
                ['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_id' => null],
                ['assigned_by' => $user->id],
            );
        }
    }
}
