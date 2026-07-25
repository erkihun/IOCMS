<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace module tables — SOB/SEL/COM/DSP/MVO (LLD §5.28-5.32). Feature-gated
 * (SDD ADR-020, decision D-005): the tables exist but onboarding/ledger/payout
 * operations are disabled until governance enables `feature('marketplace')`.
 * Commission snapshotted at order-item level (BR-028); seller ledger append-only
 * with balance buckets (BR-029); payout maker-checker (BR-024/030); seller
 * isolation is a security boundary (BR-026/032).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- SOB ---
        Schema::create('seller_applications', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('legal_name', 191);
            $t->string('status', 24)->default('draft'); // SellerState
            $t->foreignId('applicant_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampsTz();
        });
        Schema::create('seller_application_contacts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_application_id')->constrained('seller_applications')->cascadeOnDelete();
            $t->string('name', 191);
            $t->string('email', 191)->nullable();
            $t->string('phone', 32)->nullable();
            $t->timestampsTz();
        });
        Schema::create('sellers', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('legal_name', 191);
            $t->string('status', 24)->default('approved'); // approved|suspended|closed
            $t->timestampsTz();
        });
        Schema::create('seller_documents', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_application_id')->nullable()->constrained('seller_applications')->nullOnDelete();
            $t->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $t->string('type', 60);
            $t->string('path', 255);
            $t->string('scan_status', 24)->default('pending');
            $t->timestamp('expires_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('seller_verification_checks', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_application_id')->constrained('seller_applications')->cascadeOnDelete();
            $t->string('check', 60);
            $t->string('result', 24);
            $t->foreignId('reviewer_id')->nullable();
            $t->timestampsTz();
        });
        Schema::create('seller_status_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $t->foreignId('seller_application_id')->nullable()->constrained('seller_applications')->nullOnDelete();
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24);
            $t->timestampTz('created_at');
        });
        Schema::create('seller_bank_accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->text('account_number'); // encrypted at rest (NFR-SE-015)
            $t->string('bank_name', 120);
            $t->string('status', 24)->default('pending'); // pending|verified
            $t->timestampsTz();
        });

        // --- SEL ---
        Schema::create('seller_users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestampsTz();
            $t->unique(['seller_id', 'user_id']);
        });
        Schema::create('seller_roles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->string('code', 60);
            $t->timestampsTz();
        });
        Schema::create('seller_role_permissions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_role_id')->constrained('seller_roles')->cascadeOnDelete();
            $t->string('permission_code', 120);
            $t->timestampsTz();
        });
        Schema::create('seller_stores', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->string('name', 191);
            $t->string('status', 24)->default('active');
            $t->timestampsTz();
        });
        Schema::create('seller_offers', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->decimal('price', 20, 4);
            $t->char('currency', 3)->default('ETB');
            $t->string('status', 24)->default('pending_review');
            $t->timestampsTz();
        });
        Schema::create('seller_offer_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_offer_id')->constrained('seller_offers')->cascadeOnDelete();
            $t->string('event', 40);
            $t->timestampTz('created_at');
        });
        Schema::create('seller_service_metrics', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->string('metric', 60);
            $t->decimal('value', 20, 6);
            $t->date('period');
            $t->timestampsTz();
        });

        // --- COM ---
        Schema::create('commission_rules', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('name', 120);
            $t->decimal('rate', 20, 6);
            $t->string('status', 24)->default('active');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('effective_from')->nullable();
            $t->timestamp('effective_to')->nullable();
            $t->foreignId('approved_by')->nullable();
            $t->timestampsTz();
        });
        Schema::create('commission_rule_scopes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('commission_rule_id')->constrained('commission_rules')->cascadeOnDelete();
            $t->string('scope_type', 40); // seller|category|product
            $t->string('scope_value', 120);
            $t->timestampsTz();
        });
        Schema::create('commission_snapshots', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->foreignId('commission_rule_id')->nullable();
            $t->decimal('applied_rate', 20, 6);
            $t->decimal('commission_amount', 20, 4);
            $t->char('currency', 3)->default('ETB');
            $t->timestampsTz();

            // BR-028: one commission snapshot per order line.
            $t->unique('order_item_id');
        });
        Schema::create('seller_ledger_entries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->string('type', 40); // sale|commission|fee|refund|dispute|reserve|adjustment|payout
            $t->string('bucket', 24)->default('pending'); // pending|available|reserved|payable|paid
            $t->decimal('amount', 20, 4); // signed
            $t->char('currency', 3)->default('ETB');
            $t->string('source_type', 60);
            $t->string('source_reference', 120);
            $t->timestampTz('posted_at');

            // Append-only; ledger.duplicate_source dedup.
            $t->unique(['seller_id', 'source_type', 'source_reference', 'type']);
        });
        Schema::create('seller_reserves', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->decimal('amount', 20, 4);
            $t->string('reason', 120);
            $t->string('status', 24)->default('held');
            $t->timestamp('release_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('seller_statements', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->decimal('net_amount', 20, 4);
            $t->timestampsTz();
        });
        Schema::create('seller_statement_lines', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_statement_id')->constrained('seller_statements')->cascadeOnDelete();
            $t->foreignId('seller_ledger_entry_id')->constrained('seller_ledger_entries')->cascadeOnDelete();
        });
        Schema::create('seller_payouts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('payout_number', 40)->unique();
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->string('status', 24)->default('draft'); // PayoutStatus
            $t->decimal('amount', 20, 4);
            $t->char('currency', 3)->default('ETB');
            $t->foreignId('maker_id')->nullable();
            $t->foreignId('checker_id')->nullable();
            $t->string('idempotency_key', 120)->nullable();
            $t->string('provider_reference', 191)->nullable();
            $t->timestampsTz();

            $t->unique('idempotency_key');
        });
        Schema::create('payout_approvals', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_payout_id')->constrained('seller_payouts')->cascadeOnDelete();
            $t->foreignId('actor_id');
            $t->string('decision', 24);
            $t->timestampTz('decided_at');
        });
        Schema::create('payout_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_payout_id')->constrained('seller_payouts')->cascadeOnDelete();
            $t->string('event', 40);
            $t->json('detail')->nullable();
            $t->timestampTz('created_at');
        });
        Schema::create('payout_reconciliation_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_payout_id')->constrained('seller_payouts')->cascadeOnDelete();
            $t->string('match_status', 24)->default('unmatched');
            $t->timestampsTz();
        });

        // --- DSP ---
        Schema::create('disputes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->string('case_number', 40)->unique();
            $t->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $t->string('type', 40);
            $t->string('status', 24)->default('open');
            $t->timestamp('response_due_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('dispute_parties', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->string('party_type', 24); // customer|seller|platform
            $t->unsignedBigInteger('party_id')->nullable();
            $t->timestampsTz();
        });
        Schema::create('dispute_messages', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->string('visibility', 24)->default('parties');
            $t->foreignId('author_id')->nullable();
            $t->text('body');
            $t->timestampsTz();
        });
        Schema::create('dispute_evidence', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->string('path', 255);
            $t->string('scan_status', 24)->default('pending');
            $t->timestampsTz();
        });
        Schema::create('dispute_deadlines', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->string('kind', 40);
            $t->timestamp('due_at');
            $t->timestampsTz();
        });
        Schema::create('dispute_decisions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->string('outcome', 40); // refund|partial_refund|replacement|seller_release|reserve
            $t->foreignId('decided_by');
            $t->text('rationale')->nullable();
            $t->timestampTz('decided_at');
        });
        Schema::create('dispute_adjustments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->decimal('amount', 20, 4);
            $t->string('applied_to', 40);
            $t->string('status', 24)->default('pending');
            $t->timestampsTz();
        });
        Schema::create('dispute_appeals', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->string('status', 24)->default('open');
            $t->timestampsTz();
        });
        Schema::create('dispute_status_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24);
            $t->timestampTz('created_at');
        });

        // --- MVO ---
        Schema::create('seller_orders', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('public_id')->unique();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $t->string('status', 30)->default('pending');
            $t->decimal('subtotal', 20, 4)->default(0);
            $t->char('currency', 3)->default('ETB');
            $t->timestampsTz();

            $t->unique(['order_id', 'seller_id']);
        });
        Schema::create('seller_order_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $t->decimal('allocated_total', 20, 4);
            $t->timestampsTz();
        });
        Schema::create('fulfillment_groups', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $t->string('status', 30)->default('pending');
            $t->timestampsTz();
        });
        Schema::create('fulfillment_group_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('fulfillment_group_id')->constrained('fulfillment_groups')->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $t->unsignedInteger('quantity');
        });
        Schema::create('aggregate_order_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->string('event', 40);
            $t->json('detail')->nullable();
            $t->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        foreach ([
            'aggregate_order_events', 'fulfillment_group_items', 'fulfillment_groups',
            'seller_order_items', 'seller_orders',
            'dispute_status_events', 'dispute_appeals', 'dispute_adjustments', 'dispute_decisions',
            'dispute_deadlines', 'dispute_evidence', 'dispute_messages', 'dispute_parties', 'disputes',
            'payout_reconciliation_items', 'payout_events', 'payout_approvals', 'seller_payouts',
            'seller_statement_lines', 'seller_statements', 'seller_reserves', 'seller_ledger_entries',
            'commission_snapshots', 'commission_rule_scopes', 'commission_rules',
            'seller_service_metrics', 'seller_offer_events', 'seller_offers', 'seller_stores',
            'seller_role_permissions', 'seller_roles', 'seller_users',
            'seller_bank_accounts', 'seller_status_events', 'seller_verification_checks',
            'seller_documents', 'sellers', 'seller_application_contacts', 'seller_applications',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
