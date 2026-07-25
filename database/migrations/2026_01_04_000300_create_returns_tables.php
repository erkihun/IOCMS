<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RET module tables (LLD §5.17). Return requests/items, evidence, authorization,
 * inspection, disposition, recall (FR-RET-001..014, BR-012/038). Refund
 * initiation is linked to an approved return outcome (refunds live in PAY).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('rma_number', 40)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('requested'); // ReturnStatus
            $table->string('reason', 191)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->index(['order_id', 'status']);
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('quantity_approved')->default(0);
            $table->string('condition', 40)->nullable();
            $table->timestampsTz();
        });

        Schema::create('return_evidence', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 255);
            $table->string('scan_status', 24)->default('pending');
            $table->timestampsTz();
        });

        Schema::create('return_authorizations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->string('decision', 24); // approved|rejected
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable();
            $table->timestampTz('decided_at');
        });

        Schema::create('return_inspections', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('return_item_id')->constrained('return_items')->cascadeOnDelete();
            $table->string('outcome', 40); // restock|damaged|quarantine|dispose
            $table->text('notes')->nullable();
            $table->foreignId('inspected_by')->nullable();
            $table->timestampTz('inspected_at');
        });

        Schema::create('return_dispositions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('return_item_id')->constrained('return_items')->cascadeOnDelete();
            $table->string('disposition', 40); // restock|refurbish|quarantine|damage|supplier_return|disposal
            $table->unsignedInteger('quantity');
            $table->timestampsTz();
        });

        Schema::create('return_status_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 191)->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('recall_campaigns', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('name', 191);
            $table->string('status', 24)->default('open');
            $table->text('reason')->nullable();
            $table->timestampsTz();
        });

        Schema::create('recall_targets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('recall_campaign_id')->constrained('recall_campaigns')->cascadeOnDelete();
            $table->string('target_type', 24); // product|batch|serial
            $table->string('target_value', 191);
            $table->timestampsTz();
        });

        Schema::create('recall_notifications', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('recall_campaign_id')->constrained('recall_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recall_notifications');
        Schema::dropIfExists('recall_targets');
        Schema::dropIfExists('recall_campaigns');
        Schema::dropIfExists('return_status_events');
        Schema::dropIfExists('return_dispositions');
        Schema::dropIfExists('return_inspections');
        Schema::dropIfExists('return_authorizations');
        Schema::dropIfExists('return_evidence');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
    }
};
