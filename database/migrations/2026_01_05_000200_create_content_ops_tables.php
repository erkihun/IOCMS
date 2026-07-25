<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS + MKT + RPT + DIE + PWA + OPS module tables (LLD §5.21-5.27). Versioned
 * content/policies (FR-CMS-002), segments/campaigns/loyalty, governed reports +
 * exports (BR-044), import staging with rollback (FR-DIE-005), PWA drafts,
 * operations (health/backup/restore/jobs).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- CMS ---
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('key', 120)->unique();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestampsTz();
        });
        Schema::create('content_page_versions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('content_page_id')->constrained('content_pages')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('status', 24)->default('draft');
            $table->timestamp('effective_at')->nullable();
            $table->timestampsTz();
            $table->unique(['content_page_id', 'version_no']);
        });
        Schema::create('content_translations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('content_page_version_id')->constrained('content_page_versions')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->longText('body');
            $table->timestampsTz();
            $table->unique(['content_page_version_id', 'locale']);
        });
        Schema::create('navigation_menus', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('code', 60)->unique();
            $t->timestampsTz();
        });
        Schema::create('navigation_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('navigation_menu_id')->constrained('navigation_menus')->cascadeOnDelete();
            $t->string('label', 120);
            $t->string('url', 255);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestampsTz();
        });
        Schema::create('homepage_sections', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('type', 40);
            $t->json('config')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->string('status', 24)->default('active');
            $t->timestampsTz();
        });
        Schema::create('banners', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('title', 191);
            $t->string('image_path', 255)->nullable();
            $t->string('status', 24)->default('draft');
            $t->timestampsTz();
        });
        Schema::create('banner_placements', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
            $t->string('slot', 60);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('seo_redirects', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('from_path', 255)->unique();
            $t->string('to_path', 255);
            $t->unsignedSmallInteger('status_code')->default(301);
            $t->timestampsTz();
        });

        // --- MKT ---
        Schema::create('customer_segments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('name', 120);
            $t->string('status', 24)->default('active');
            $t->timestampsTz();
        });
        Schema::create('segment_rules', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('customer_segment_id')->constrained('customer_segments')->cascadeOnDelete();
            $t->json('condition');
            $t->timestampsTz();
        });
        Schema::create('segment_memberships', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('customer_segment_id')->constrained('customer_segments')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestampsTz();
            $t->unique(['customer_segment_id', 'user_id']);
        });
        Schema::create('marketing_campaigns', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('name', 191);
            $t->string('channel', 24);
            $t->string('status', 24)->default('draft');
            $t->timestamp('scheduled_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('campaign_audiences', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('marketing_campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $t->foreignId('customer_segment_id')->nullable()->constrained('customer_segments')->nullOnDelete();
            $t->timestampsTz();
        });
        Schema::create('campaign_messages', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('marketing_campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $t->string('locale', 5);
            $t->longText('body');
            $t->timestampsTz();
        });
        Schema::create('campaign_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('marketing_campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $t->string('event', 40);
            $t->foreignId('user_id')->nullable();
            $t->timestampTz('created_at');
        });
        Schema::create('abandoned_cart_schedules', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $t->timestamp('remind_at');
            $t->string('status', 24)->default('scheduled');
            $t->timestampsTz();
        });
        Schema::create('stock_alert_subscriptions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->timestampsTz();
            $t->unique(['user_id', 'product_variant_id']);
        });
        Schema::create('price_alert_subscriptions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->decimal('target_price', 20, 4)->nullable();
            $t->timestampsTz();
        });
        Schema::create('loyalty_accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->integer('balance')->default(0);
            $t->timestampsTz();
        });
        Schema::create('loyalty_ledger_entries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $t->integer('points'); // signed
            $t->string('reason', 120);
            $t->string('source_reference', 120)->nullable();
            $t->timestampTz('created_at');
            $t->unique(['loyalty_account_id', 'source_reference']); // BR loyalty.duplicate_source
        });
        Schema::create('referral_programs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('code', 60)->unique();
            $t->string('status', 24)->default('active');
            $t->timestampsTz();
        });
        Schema::create('referral_codes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('referral_program_id')->constrained('referral_programs')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('code', 60)->unique();
            $t->timestampsTz();
        });
        Schema::create('referral_rewards', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('referral_code_id')->constrained('referral_codes')->cascadeOnDelete();
            $t->string('status', 24)->default('pending');
            $t->timestampsTz();
        });

        // --- RPT ---
        Schema::create('report_definitions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('key', 120)->unique();
            $t->string('name', 191);
            $t->json('metric_definition')->nullable();
            $t->timestampsTz();
        });
        Schema::create('report_runs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('report_definition_id')->constrained('report_definitions')->cascadeOnDelete();
            $t->string('status', 24)->default('queued');
            $t->foreignId('requested_by')->nullable();
            $t->timestampsTz();
        });
        Schema::create('report_run_parameters', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('report_run_id')->constrained('report_runs')->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('value', 255);
        });
        Schema::create('report_exports', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('report_run_id')->constrained('report_runs')->cascadeOnDelete();
            $t->string('format', 12);
            $t->string('disk', 40)->default('local');
            $t->string('path', 255)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('report_schedules', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('report_definition_id')->constrained('report_definitions')->cascadeOnDelete();
            $t->string('cron', 60);
            $t->string('status', 24)->default('active');
            $t->timestampsTz();
        });
        Schema::create('report_projection_versions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('projection', 80);
            $t->unsignedBigInteger('version');
            $t->timestamp('built_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('analytics_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('event', 60);
            $t->json('properties')->nullable();
            $t->timestampTz('created_at');
            $t->index(['event', 'created_at']);
        });

        // --- DIE ---
        Schema::create('import_templates', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('key', 120)->unique();
            $t->string('target', 60);
            $t->unsignedInteger('current_version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('import_template_versions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('import_template_id')->constrained('import_templates')->cascadeOnDelete();
            $t->unsignedInteger('version_no');
            $t->json('columns');
            $t->timestampsTz();
            $t->unique(['import_template_id', 'version_no']);
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('import_template_id')->constrained('import_templates')->cascadeOnDelete();
            $t->string('status', 24)->default('staged'); // staged|validated|previewed|committed|rolled_back|failed
            $t->string('source_path', 255)->nullable();
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('error_rows')->default(0);
            $t->foreignId('initiated_by')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestampsTz();
        });
        Schema::create('import_rows', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->json('data');
            $t->string('status', 24)->default('pending'); // pending|valid|invalid|committed
            $t->timestampsTz();
        });
        Schema::create('import_errors', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->string('column', 60)->nullable();
            $t->string('error_code', 80);
            $t->string('message', 255);
            $t->timestampsTz();
        });
        Schema::create('export_batches', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('target', 60);
            $t->string('status', 24)->default('queued');
            $t->boolean('contains_restricted')->default(false);
            $t->foreignId('authorized_by')->nullable();
            $t->timestampsTz();
        });
        Schema::create('data_exchange_files', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('disk', 40)->default('local');
            $t->string('path', 255);
            $t->char('checksum', 64)->nullable();
            $t->timestampsTz();
        });

        // --- PWA ---
        Schema::create('offline_drafts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 40);
            $t->json('payload');
            $t->string('status', 24)->default('pending');
            $t->timestampsTz();
        });
        Schema::create('service_worker_releases', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('version', 40)->unique();
            $t->string('status', 24)->default('active');
            $t->timestampsTz();
        });

        // --- OPS ---
        Schema::create('job_executions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('job', 191);
            $t->string('queue', 60)->nullable();
            $t->string('status', 24); // running|completed|failed|dead_letter
            $t->string('correlation_id', 36)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestampsTz();
            $t->index(['status', 'created_at']);
        });
        Schema::create('health_check_results', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('component', 60);
            $t->string('status', 24);
            $t->unsignedInteger('latency_ms')->nullable();
            $t->timestampTz('checked_at');
            $t->index(['component', 'checked_at']);
        });
        Schema::create('scheduled_task_runs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('task', 120);
            $t->string('status', 24);
            $t->timestamp('ran_at');
            $t->timestampsTz();
        });
        Schema::create('backup_runs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('type', 24); // database|files|config
            $t->string('status', 24)->default('running');
            $t->string('location', 255)->nullable();
            $t->char('checksum', 64)->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('restore_tests', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('backup_run_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $t->string('status', 24)->default('pending'); // pending|passed|failed
            $t->text('notes')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('operational_alerts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('severity', 16);
            $t->string('title', 191);
            $t->string('status', 24)->default('open');
            $t->foreignId('acknowledged_by')->nullable();
            $t->timestampsTz();
        });
        Schema::create('deployment_records', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('version', 60);
            $t->string('status', 24);
            $t->timestampTz('deployed_at');
            $t->timestampsTz();
        });
        Schema::create('maintenance_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('event', 40);
            $t->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        foreach ([
            'maintenance_events', 'deployment_records', 'operational_alerts', 'restore_tests', 'backup_runs',
            'scheduled_task_runs', 'health_check_results', 'job_executions',
            'service_worker_releases', 'offline_drafts',
            'data_exchange_files', 'export_batches', 'import_errors', 'import_rows', 'import_batches',
            'import_template_versions', 'import_templates',
            'analytics_events', 'report_projection_versions', 'report_schedules', 'report_exports',
            'report_run_parameters', 'report_runs', 'report_definitions',
            'referral_rewards', 'referral_codes', 'referral_programs',
            'loyalty_ledger_entries', 'loyalty_accounts', 'price_alert_subscriptions',
            'stock_alert_subscriptions', 'abandoned_cart_schedules', 'campaign_events',
            'campaign_messages', 'campaign_audiences', 'marketing_campaigns', 'segment_memberships',
            'segment_rules', 'customer_segments',
            'seo_redirects', 'banner_placements', 'banners', 'homepage_sections',
            'navigation_items', 'navigation_menus', 'content_translations',
            'content_page_versions', 'content_pages',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
