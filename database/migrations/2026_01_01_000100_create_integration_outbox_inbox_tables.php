<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * INT module — transactional outbox and idempotent inbox (LLD §5.25 / Appendix A,
 * SDD ADR-006). A material state change and its outbox record commit atomically;
 * delivery is at-least-once and business effects are exactly-once via event IDs
 * and inbox deduplication (BR-047, BR-048).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('event_name', 120);
            $table->string('aggregate_type', 120);
            $table->string('aggregate_public_id', 36)->nullable();
            $table->unsignedInteger('aggregate_version')->default(1);
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->string('status', 24)->default('pending'); // pending|published|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'available_at']);
            $table->index(['aggregate_type', 'aggregate_public_id']);
            $table->index('correlation_id');
        });

        Schema::create('inbox_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('source', 80); // provider or internal source
            $table->string('external_event_id', 191);
            $table->string('event_type', 120);
            $table->json('payload');
            $table->string('correlation_id', 36)->nullable();
            $table->string('status', 24)->default('received'); // received|processed|pending|unknown|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestampsTz();

            // Deduplication: one recorded event per (source, external id) (FR-PAY-007, BR-020).
            $table->unique(['source', 'external_event_id']);
            $table->index(['status', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_events');
        Schema::dropIfExists('outbox_events');
    }
};
