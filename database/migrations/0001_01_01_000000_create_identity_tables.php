<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IAM module tables (LLD §5.1 / Appendix A). Identity lifecycle, authenticators,
 * sessions, roles/permissions, record scope, verification and recovery tokens,
 * login attempts. Enforces FR-IAM-001..012 and business rules BR-025/BR-026/BR-046.
 *
 * Notes:
 *  - Public IDs are UUIDv7 (CHAR(36)); internal BIGINT keys never authorize (BR-046).
 *  - The LLD `sessions` table (active-session management, FR-IAM-008) is named
 *    `user_sessions` to avoid clashing with Laravel's framework session store
 *    (decision recorded in decision-register.md D-013).
 *  - Normalized identifier uniqueness prevents duplicate active identities
 *    (FR-IAM-001) via a unique index on (type, normalized_value).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('type', 24)->default('customer');   // UserType
            $table->string('status', 24)->default('pending');  // UserStatus
            $table->string('display_name', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->char('locale', 5)->default('en');
            $table->string('password_hash', 255)->nullable();
            $table->boolean('mfa_enrolled')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['type', 'status']);
        });

        Schema::create('user_identifiers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);                         // IdentifierType
            $table->string('normalized_value', 191);            // lower/trimmed/e164
            $table->string('display_value', 191);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            // FR-IAM-001: prevent duplicate active identities for the same
            // normalized identifier. Enforced at DB level for concurrency safety.
            $table->unique(['type', 'normalized_value']);
            $table->index('user_id');
        });

        Schema::create('contact_verifications', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16);          // email|phone
            $table->string('token_hash', 64);       // hashed single-use token
            $table->string('status', 16)->default('pending'); // pending|used|expired
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'channel', 'status']);
            $table->unique('token_hash');
        });

        Schema::create('password_histories', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('password_hash', 255);
            $table->timestampsTz();

            $table->index('user_id');
        });

        Schema::create('authenticators', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24);             // AuthenticatorType
            $table->string('status', 16)->default('pending'); // AuthenticatorStatus
            $table->text('secret')->nullable();     // encrypted at rest (NFR-SE-015)
            $table->string('label', 120)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'type', 'status']);
        });

        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_token_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'revoked_at']);
            $table->unique('session_token_hash');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('scope_type', 40)->default('global'); // ScopeType
            $table->string('status', 16)->default('active');
            $table->boolean('system_owned')->default(false);
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 120)->unique();
            $table->string('resource', 80);
            $table->string('action', 40);
            $table->string('description', 255)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestampsTz();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type', 40)->default('global');
            $table->string('scope_id', 36)->nullable();       // public id of the scoped record
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('assigned_by')->nullable();
            $table->timestampsTz();

            // One active assignment of a role at a scope per user.
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id']);
            $table->index(['user_id']);
        });

        Schema::create('account_recovery_tokens', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 16)->default('pending'); // pending|used|expired
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
        });

        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('identifier_hash', 64);  // hashed normalized identifier
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 16);            // success|failed|throttled
            $table->string('reason', 80)->nullable();
            $table->timestampsTz();

            $table->index(['identifier_hash', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        // Framework session store (Laravel `database` session driver) — distinct
        // from the domain `user_sessions` table above.
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('account_recovery_tokens');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('authenticators');
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('contact_verifications');
        Schema::dropIfExists('user_identifiers');
        Schema::dropIfExists('users');
    }
};
