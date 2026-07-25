<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADM module tables (LLD §5.2 / Appendix A): typed, versioned, audited
 * configuration; feature flags with activation windows; document templates;
 * numbering rules; maintenance windows (FR-ADM-001..008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('key', 160)->unique();
            $table->string('type', 24);              // string|int|bool|decimal|json|secret_ref
            $table->text('value')->nullable();       // never a raw secret (NFR-SE-016)
            $table->string('category', 80)->default('general');
            $table->string('status', 24)->default('active');
            $table->boolean('is_significant')->default(false); // requires versioning + approval
            $table->boolean('is_secret_ref')->default(false);
            $table->json('metadata')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['category', 'status']);
        });

        Schema::create('setting_versions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('setting_id')->constrained('system_settings')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('status', 24)->default('pending'); // pending|approved|active|superseded
            $table->text('value')->nullable();
            $table->char('checksum', 64);
            $table->timestamp('effective_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestampsTz();

            $table->unique(['setting_id', 'version_no']);
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('key', 160)->unique();
            $table->string('name', 191);
            $table->boolean('is_enabled')->default(false);
            $table->string('status', 24)->default('active'); // active|disabled|archived
            $table->string('owner', 120)->nullable();
            $table->string('scope_type', 40)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('feature_flag_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('feature_flag_id')->constrained('feature_flags')->cascadeOnDelete();
            $table->string('code', 120);
            $table->unsignedInteger('priority')->default(0);
            $table->json('condition_json')->nullable();
            $table->json('effect_json')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('document_templates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('key', 160)->unique();
            $table->string('name', 191);
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('current_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('document_template_versions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('locale', 5)->default('en');
            $table->string('status', 24)->default('draft');
            $table->longText('content');
            $table->char('checksum', 64);
            $table->timestamp('effective_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestampsTz();

            $table->unique(['document_template_id', 'version_no', 'locale']);
        });

        Schema::create('numbering_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('key', 80)->unique(); // order|shipment|return|case|payout|batch
            $table->string('prefix', 20)->default('');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedInteger('padding')->default(6);
            $table->string('reset_period', 20)->nullable(); // none|daily|monthly|yearly
            $table->string('period_key', 20)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();
        });

        Schema::create('maintenance_windows', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('title', 191);
            $table->text('customer_message')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 24)->default('scheduled');
            $table->foreignId('created_by')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('numbering_rules');
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('feature_flag_rules');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('setting_versions');
        Schema::dropIfExists('system_settings');
    }
};
