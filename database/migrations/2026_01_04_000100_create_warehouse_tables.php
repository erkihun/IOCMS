<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHM module tables (LLD §5.15). Warehouses/bins, suppliers, purchase orders,
 * goods receipts, pick/pack tasks, packages, stock transfers (FR-WHM-001..010).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('status', 24)->default('active');
            $table->json('service_coverage')->nullable();
            $table->timestampsTz();
        });

        Schema::create('bins', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('zone', 40)->nullable();
            $table->timestampsTz();

            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('name', 191);
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestampsTz();
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('po_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('status', 24)->default('draft'); // draft|approved|receiving|closed
            $table->timestampsTz();
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->decimal('unit_cost', 20, 4)->nullable();
            $table->timestampsTz();
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('status', 24)->default('open');
            $table->timestampsTz();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->timestampsTz();
        });

        Schema::create('pick_tasks', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('status', 24)->default('pending'); // pending|picking|picked|packed
            $table->foreignId('assigned_to')->nullable();
            $table->timestampsTz();
        });

        Schema::create('pick_task_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('pick_task_id')->constrained('pick_tasks')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity_to_pick');
            $table->unsignedInteger('quantity_picked')->default(0);
            $table->timestampsTz();
        });

        Schema::create('packages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('pick_task_id')->nullable()->constrained('pick_tasks')->nullOnDelete();
            $table->string('status', 24)->default('open'); // open|sealed
            $table->decimal('weight_grams', 20, 6)->nullable();
            $table->foreignId('packed_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('package_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestampsTz();
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->string('status', 24)->default('requested');
            $table->timestampsTz();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity_dispatched');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('pick_task_items');
        Schema::dropIfExists('pick_tasks');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('bins');
        Schema::dropIfExists('warehouses');
    }
};
