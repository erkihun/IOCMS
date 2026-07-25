<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ORD module tables (LLD §5.12). Immutable order snapshots (BR-002/016),
 * independent status (BR-003), line-level quantities (FR-ORD-003), status
 * events, notes, cancellations, invoices. Human-readable order number separate
 * from the UUID public id (FR-CHK-013, BR-046).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('order_number', 40)->unique();     // governed human reference
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_email', 191)->nullable();
            $table->string('status', 30)->default('pending_payment'); // OrderStatus
            $table->char('currency', 3)->default('ETB');
            $table->decimal('items_subtotal', 20, 4)->default(0);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('tax_total', 20, 4)->default(0);
            $table->decimal('delivery_total', 20, 4)->default(0);
            $table->decimal('grand_total', 20, 4)->default(0);
            $table->string('payment_method', 60)->nullable();
            $table->string('delivery_method', 60)->nullable();
            $table->string('terms_version', 40)->nullable();
            $table->json('quote_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('correlation_id', 36)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id')->nullable(); // reference only
            $table->unsignedBigInteger('seller_id')->nullable();
            // Immutable snapshot columns (BR-002).
            $table->string('product_name', 191);
            $table->string('sku', 80);
            $table->json('selected_options')->nullable();
            $table->string('image_reference', 255)->nullable();
            $table->decimal('unit_price', 20, 4);
            $table->decimal('discount', 20, 4)->default(0);
            $table->decimal('tax', 20, 4)->default(0);
            $table->decimal('line_total', 20, 4);
            $table->char('currency', 3)->default('ETB');
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_allocated')->default(0);
            $table->unsignedInteger('quantity_cancelled')->default(0);
            $table->unsignedInteger('quantity_fulfilled')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->unsignedInteger('quantity_refunded')->default(0);
            $table->unsignedInteger('return_policy_version')->nullable();
            $table->unsignedInteger('return_window_days')->nullable();
            $table->timestampsTz();

            $table->index('order_id');
        });

        Schema::create('order_addresses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 16); // delivery|billing
            $table->json('snapshot'); // immutable address snapshot
            $table->timestampsTz();

            $table->unique(['order_id', 'type']);
        });

        Schema::create('order_adjustments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 40); // coupon|promotion|credit
            $table->string('reference', 80);
            $table->decimal('amount', 20, 4);
            $table->timestampsTz();
        });

        Schema::create('order_status_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 191)->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->timestampTz('created_at');

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_notes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('visibility', 24)->default('internal'); // internal|seller|customer
            $table->text('body');
            $table->foreignId('author_id')->nullable();
            $table->timestampsTz();
        });

        Schema::create('order_cancellation_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->json('lines'); // {order_item_id: quantity}
            $table->string('reason', 191);
            $table->string('status', 24)->default('requested');
            $table->foreignId('requested_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('total', 20, 4);
            $table->char('currency', 3)->default('ETB');
            $table->json('snapshot');
            $table->timestampTz('issued_at');
            $table->timestampsTz();
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 20, 4);
            $table->decimal('line_total', 20, 4);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_cancellation_requests');
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_adjustments');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
