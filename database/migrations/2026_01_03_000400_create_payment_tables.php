<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAY module tables (LLD §5.13). Payment attempts, signed/idempotent provider
 * events (FR-PAY-006/007, BR-020), refunds (BR-005 no over-refund), an
 * append-only financial ledger (FR-PAY-014), reconciliation, COD collections
 * (FR-PAY-012), and a payment idempotency ledger. No card/CVV/OTP columns
 * anywhere (FR-PAY-016, BR-023).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('method', 60);              // online|cod|bank_transfer
            $table->string('provider', 60)->nullable();
            $table->string('status', 30)->default('pending'); // PaymentStatus
            $table->decimal('amount', 20, 4);
            $table->decimal('captured_amount', 20, 4)->default(0);
            $table->decimal('refunded_amount', 20, 4)->default(0);
            $table->char('currency', 3)->default('ETB');
            $table->string('provider_reference', 191)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('correlation_id', 36)->nullable();
            $table->timestampsTz();

            $table->index(['order_id', 'status']);
            $table->index('provider_reference');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('status', 30)->default('initiated');
            $table->string('provider_reference', 191)->nullable();
            $table->json('request_snapshot')->nullable();  // redacted; never secrets
            $table->timestampsTz();

            $table->unique(['payment_id', 'attempt_no']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 60);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 60);
            $table->string('status_applied', 30)->nullable();
            $table->decimal('amount', 20, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('payload')->nullable();          // redacted provider payload
            $table->string('signature_status', 24)->default('unverified');
            $table->string('processing_status', 24)->default('pending'); // pending|applied|ignored|unknown
            $table->timestampsTz();

            // BR-020 / FR-PAY-007: one recorded event per provider event id.
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['processing_status']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 30)->default('pending'); // pending|approved|settled|failed
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3)->default('ETB');
            $table->string('reason', 191)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->foreignId('maker_id')->nullable();
            $table->foreignId('checker_id')->nullable();
            $table->string('return_reference', 60)->nullable(); // linked approved return
            $table->timestampsTz();

            $table->index(['payment_id', 'status']);
        });

        Schema::create('refund_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('detail')->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('financial_ledger_entries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('account', 60);   // sales|refunds|fees|cash_cod|...
            $table->string('entry_type', 8); // debit|credit
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3)->default('ETB');
            $table->string('source_type', 60);
            $table->string('source_reference', 80);
            $table->string('correlation_id', 36)->nullable();
            $table->timestampTz('posted_at');

            // Append-only (FR-PAY-014); corrections are compensating entries.
            $table->index(['account', 'posted_at']);
            $table->index(['source_type', 'source_reference']);
        });

        Schema::create('reconciliation_batches', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('provider', 60);
            $table->string('status', 24)->default('open');
            $table->date('statement_date')->nullable();
            $table->timestampsTz();
        });

        Schema::create('reconciliation_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('reconciliation_batch_id')->constrained('reconciliation_batches')->cascadeOnDelete();
            $table->string('provider_reference', 191);
            $table->decimal('provider_amount', 20, 4)->nullable();
            $table->decimal('internal_amount', 20, 4)->nullable();
            $table->string('match_status', 24)->default('unmatched'); // matched|missing|duplicate|mismatch
            $table->timestampsTz();

            $table->index(['reconciliation_batch_id', 'match_status']);
        });

        Schema::create('cod_collections', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->unsignedBigInteger('collecting_agent_id')->nullable();
            $table->decimal('amount', 20, 4);
            $table->string('status', 24)->default('pending'); // pending|collected|failed|reconciled
            $table->timestamp('collected_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('payment_idempotency_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('idempotency_key', 120);
            $table->string('operation', 60);
            $table->char('request_fingerprint', 64);
            $table->json('response')->nullable();
            $table->timestampsTz();

            $table->unique(['idempotency_key', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_idempotency_records');
        Schema::dropIfExists('cod_collections');
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliation_batches');
        Schema::dropIfExists('financial_ledger_entries');
        Schema::dropIfExists('refund_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
