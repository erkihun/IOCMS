<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRI module tables (LLD §5.9). Price lists/prices with history, tax classes/
 * rules (no hard-coded rates — FR-PRI-008), promotions, coupons with usage
 * limits + reservations (coupon concurrency), price approvals. Money is
 * DECIMAL(20,4) + currency (LLD §3.5, no float).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('code', 80)->unique();
            $table->char('currency', 3)->default('ETB');
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        Schema::create('prices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('amount', 20, 4);
            $table->decimal('compare_at_amount', 20, 4)->nullable();
            $table->char('currency', 3)->default('ETB');
            $table->string('status', 24)->default('active'); // draft|pending|active|superseded
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestampsTz();

            $table->index(['product_variant_id', 'status', 'effective_from']);
        });

        Schema::create('tax_classes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->boolean('is_inclusive')->default(false);
            $table->timestampsTz();
        });

        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('tax_class_id')->constrained('tax_classes')->cascadeOnDelete();
            $table->string('jurisdiction', 80)->default('ET');
            $table->decimal('rate', 20, 6); // e.g. 0.150000 — configured, never hard-coded
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestampsTz();

            $table->index(['tax_class_id', 'jurisdiction', 'effective_from']);
        });

        Schema::create('promotions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('code', 80)->unique();
            $table->string('name', 191);
            $table->string('type', 40); // fixed|percentage|bxgy|bundle|category|product|segment|delivery
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_exclusive')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('promotion_conditions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('kind', 40); // min_spend|category|product|segment
            $table->json('config');
        });

        Schema::create('promotion_benefits', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('kind', 40); // percent_off|amount_off|free_delivery|bxgy
            $table->decimal('value', 20, 6)->nullable();
            $table->json('config')->nullable();
        });

        Schema::create('promotion_exclusions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('excluded_promotion_id')->constrained('promotions')->cascadeOnDelete();

            $table->unique(['promotion_id', 'excluded_promotion_id']);
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('code', 60)->unique();
            $table->string('status', 24)->default('active');
            $table->decimal('min_spend', 20, 4)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();          // total redemptions
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('reserved_count')->default(0);       // in-flight holds
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('coupon_reservations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->string('holder_key', 120); // cart/checkout reference
            $table->string('status', 24)->default('held'); // held|consumed|released
            $table->timestamp('expires_at');
            $table->timestampsTz();

            $table->index(['coupon_id', 'status']);
        });

        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('order_reference', 40)->nullable();
            $table->timestampTz('created_at');

            // FR-PRI-005: enforce one usage per order reference (no double-count).
            $table->unique(['coupon_id', 'order_reference']);
            $table->index(['coupon_id', 'user_id']);
        });

        Schema::create('price_approvals', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('price_id')->constrained('prices')->cascadeOnDelete();
            $table->foreignId('maker_id');
            $table->foreignId('checker_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_approvals');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_reservations');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotion_exclusions');
        Schema::dropIfExists('promotion_benefits');
        Schema::dropIfExists('promotion_conditions');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_classes');
        Schema::dropIfExists('prices');
        Schema::dropIfExists('price_lists');
    }
};
