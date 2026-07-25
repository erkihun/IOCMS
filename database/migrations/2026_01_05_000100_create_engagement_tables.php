<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REV + SUP + NTF module tables (LLD §5.18/5.19/5.20). Verified-purchase reviews
 * (BR-013), product questions, support cases with controlled context, notification
 * templates + consent-aware delivery (BR-040/041).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- REV ---
        Schema::create('reviews', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 191)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('verified_purchase')->default(true);
            $table->string('status', 24)->default('pending'); // pending|approved|rejected
            $table->timestampsTz();

            // BR-013 / FR-REV-002: one active review per order line.
            $table->unique(['order_item_id', 'user_id']);
        });

        Schema::create('review_media', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('scan_status', 24)->default('pending');
            $table->timestampsTz();
        });

        Schema::create('review_responses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('responder_id')->nullable();
            $table->text('body');
            $table->timestampsTz();
        });

        Schema::create('review_votes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestampsTz();

            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('review_reports', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable();
            $table->string('reason', 120);
            $table->string('status', 24)->default('open');
            $table->timestampsTz();
        });

        Schema::create('product_rating_summaries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('review_count')->default(0);
            $table->decimal('average_rating', 4, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('product_questions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('status', 24)->default('pending');
            $table->timestampsTz();
        });

        Schema::create('product_answers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_question_id')->constrained('product_questions')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable();
            $table->text('body');
            $table->string('status', 24)->default('pending');
            $table->timestampsTz();
        });

        // --- SUP ---
        Schema::create('support_cases', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('case_number', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('category', 60);
            $table->string('priority', 24)->default('normal');
            $table->string('status', 24)->default('open');
            $table->foreignId('owner_id')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'priority']);
        });

        Schema::create('case_assignments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->foreignId('agent_id');
            $table->timestampTz('assigned_at');
        });

        Schema::create('case_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->string('visibility', 24)->default('customer'); // internal|customer
            $table->foreignId('author_id')->nullable();
            $table->text('body');
            $table->timestampsTz();
        });

        Schema::create('case_attachments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('scan_status', 24)->default('pending');
            $table->timestampsTz();
        });

        Schema::create('case_sla_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->string('event', 40);
            $table->timestampTz('created_at');
        });

        Schema::create('case_service_actions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->string('action', 60); // cancellation|refund|address_change
            $table->string('status', 24)->default('requested');
            $table->foreignId('actor_id')->nullable();
            $table->timestampsTz();
        });

        Schema::create('case_status_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->timestampTz('created_at');
        });

        // --- NTF ---
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('key', 120)->unique();
            $table->string('category', 24)->default('transactional'); // transactional|marketing
            $table->unsignedInteger('current_version')->default(1);
            $table->timestampsTz();
        });

        Schema::create('notification_template_versions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('notification_template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('locale', 5);
            $table->string('channel', 24);
            $table->string('subject', 191)->nullable();
            $table->longText('body');
            $table->string('status', 24)->default('draft');
            $table->timestampsTz();

            $table->unique(['notification_template_id', 'version_no', 'locale', 'channel']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_key', 120);
            $table->string('channel', 24);
            $table->string('category', 24)->default('transactional');
            $table->string('status', 24)->default('queued'); // queued|sent|failed|suppressed
            $table->string('dedup_key', 191)->nullable();
            $table->timestampsTz();

            // FR-NTF-007: suppress duplicate per event+recipient.
            $table->unique(['dedup_key']);
        });

        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('status', 24);
            $table->string('provider_reference', 191)->nullable();
            $table->string('failure_reason', 191)->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('notification_suppressions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('reason', 120)->nullable();
            $table->timestampsTz();
        });

        Schema::create('web_push_subscriptions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint', 500);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        foreach ([
            'web_push_subscriptions', 'notification_suppressions', 'notification_attempts',
            'notifications', 'notification_template_versions', 'notification_templates',
            'case_status_events', 'case_service_actions', 'case_sla_events', 'case_attachments',
            'case_messages', 'case_assignments', 'support_cases',
            'product_answers', 'product_questions', 'product_rating_summaries',
            'review_reports', 'review_votes', 'review_responses', 'review_media', 'reviews',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
