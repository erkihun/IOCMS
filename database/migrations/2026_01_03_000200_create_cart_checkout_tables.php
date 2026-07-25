<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRT + CHK module tables (LLD §5.10/5.11). Cart (guest + registered, BR-010 not
 * an order), saved items, checkout sessions with an idempotency ledger
 * (FR-CHK-008, LLD §3.6) and a full checkout snapshot (FR-CHK-010).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 64)->nullable();
            $table->char('currency', 3)->default('ETB');
            $table->string('status', 24)->default('active'); // active|converted|abandoned
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestampsTz();

            $table->index('user_id');
            $table->index('guest_token');
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_snapshot', 20, 4)->nullable();
            $table->char('currency', 3)->default('ETB');
            $table->timestampsTz();

            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('cart_adjustments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->string('type', 40); // coupon|promotion
            $table->string('reference', 80);
            $table->decimal('amount', 20, 4)->default(0);
            $table->timestampsTz();
        });

        Schema::create('saved_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 64)->nullable();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->timestampsTz();
        });

        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('open'); // open|placing|placed|failed
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->json('delivery_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('delivery_method', 60)->nullable();
            $table->string('payment_method', 60)->nullable();
            $table->char('currency', 3)->default('ETB');
            $table->json('quote_snapshot')->nullable();       // full price quote (FR-CHK-010)
            $table->string('cart_fingerprint', 64)->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->string('terms_version', 40)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['cart_id', 'status']);
        });

        Schema::create('checkout_step_data', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->cascadeOnDelete();
            $table->string('step', 40);
            $table->json('data');
            $table->timestampsTz();

            $table->unique(['checkout_session_id', 'step']);
        });

        Schema::create('checkout_idempotency_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('idempotency_key', 120);
            $table->string('actor_key', 120);          // user/guest scope
            $table->char('request_fingerprint', 64);
            $table->string('status', 24)->default('in_progress'); // in_progress|completed
            $table->json('response')->nullable();      // stored result for replay
            $table->timestamp('expires_at')->nullable();
            $table->timestampsTz();

            // FR-CHK-008: one order per accepted key; reuse with a different
            // fingerprint is a conflict (checkout.idempotency_conflict).
            $table->unique(['idempotency_key', 'actor_key']);
        });

        Schema::create('checkout_failures', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('checkout_session_id')->nullable()->constrained('checkout_sessions')->nullOnDelete();
            $table->string('error_code', 80);
            $table->string('correlation_id', 36)->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_failures');
        Schema::dropIfExists('checkout_idempotency_records');
        Schema::dropIfExists('checkout_step_data');
        Schema::dropIfExists('checkout_sessions');
        Schema::dropIfExists('saved_items');
        Schema::dropIfExists('cart_adjustments');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
