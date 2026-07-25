<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AUD module tables (LLD §5.3 / Appendix A). Tamper-evident audit evidence,
 * maker-checker approvals, privacy requests, incidents, legal holds, access
 * reviews (FR-AUD-001..010, BR-024/042/043).
 *
 * Audit records are append-only (FR-AUD-003, NFR-SE-021): each row carries a
 * hash chained from the previous row so tampering is detectable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('actor_user_id')->nullable();
            $table->string('actor_type', 24)->default('system');
            $table->string('action', 120);
            $table->string('resource_type', 80)->nullable();
            $table->string('resource_public_id', 36)->nullable();
            $table->string('outcome', 24)->default('success'); // success|denied|failed
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64);
            $table->timestampTz('created_at');

            $table->index(['resource_type', 'resource_public_id']);
            $table->index(['action', 'created_at']);
            $table->index('correlation_id');
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('type', 80);                 // refund|payout|role_elevation|bank_change|config
            $table->string('resource_type', 80)->nullable();
            $table->string('resource_public_id', 36)->nullable();
            $table->string('status', 24)->default('pending'); // pending|approved|rejected|cancelled
            $table->foreignId('maker_id');              // requester (cannot approve — BR-024)
            $table->foreignId('checker_id')->nullable();
            $table->json('context')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->timestampsTz();

            $table->index(['type', 'status']);
        });

        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('approval_id')->constrained('approvals')->cascadeOnDelete();
            $table->unsignedInteger('step_no');
            $table->string('status', 24)->default('pending');
            $table->foreignId('actor_id')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['approval_id', 'step_no']);
        });

        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable();
            $table->string('type', 24);                 // access|correction|export|objection|deletion
            $table->string('status', 24)->default('received');
            $table->timestamp('due_at')->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestampsTz();

            $table->index(['type', 'status']);
        });

        Schema::create('privacy_request_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('privacy_request_id')->constrained('privacy_requests')->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('detail')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('title', 191);
            $table->string('severity', 16)->default('low');   // low|medium|high|critical
            $table->string('status', 24)->default('declared'); // declared|investigating|contained|resolved
            $table->text('summary')->nullable();
            $table->foreignId('declared_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('incident_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('detail')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('legal_holds', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('resource_type', 80);
            $table->string('resource_public_id', 36)->nullable();
            $table->string('reason', 191);
            $table->string('status', 16)->default('active'); // active|released
            $table->foreignId('placed_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestampsTz();

            $table->index(['resource_type', 'resource_public_id', 'status']);
        });

        Schema::create('access_reviews', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('period', 20);
            $table->string('status', 24)->default('open');
            $table->foreignId('reviewer_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('access_review_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('access_review_id')->constrained('access_reviews')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->string('decision', 24)->nullable(); // retain|revoke|modify
            $table->text('note')->nullable();
            $table->timestampsTz();
        });

        Schema::create('evidence_files', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('owner_type', 80);
            $table->unsignedBigInteger('owner_id');
            $table->string('disk', 40)->default('local');
            $table->string('path', 255);
            $table->char('checksum', 64)->nullable();
            $table->string('scan_status', 24)->default('pending');
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
        Schema::dropIfExists('access_review_items');
        Schema::dropIfExists('access_reviews');
        Schema::dropIfExists('legal_holds');
        Schema::dropIfExists('incident_events');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('privacy_request_events');
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('audit_events');
    }
};
