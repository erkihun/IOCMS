<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * INV module tables (LLD §5.14). Inventory by variant+location (FR-INV-001),
 * on-hand vs reserved (BR-006), immutable movement ledger (BR-009), reservations
 * with expiry (FR-INV-004), lots/serials, counts, adjustments, thresholds.
 *
 * Overselling prevention (BR-008, FR-INV-007) is enforced by atomic conditional
 * updates on `available` in InventoryReservationService, plus optimistic
 * lock_version. Movements are append-only (FR-INV-014).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->default(0);   // on_hand - reserved - safety (materialized)
            $table->integer('safety_stock')->default(0);
            $table->integer('damaged')->default(0);
            $table->integer('quarantined')->default(0);
            $table->boolean('backorder_allowed')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['product_variant_id', 'warehouse_id']);
            $table->index('available');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('type', 24); // receipt|reservation|consume|release|adjustment|return|transfer|reversal
            $table->integer('quantity'); // signed
            $table->string('reason', 120)->nullable();
            $table->string('reference', 80)->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->foreignId('reverses_movement_id')->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->timestampTz('created_at');

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['reference']);
        });

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('holder_type', 40); // checkout|order
            $table->string('holder_reference', 80);
            $table->integer('quantity');
            $table->string('status', 24)->default('active'); // active|consumed|released|expired
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['holder_type', 'holder_reference']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('lot_serials', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('lot_number', 80)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 24)->default('available');
            $table->timestampsTz();

            $table->index(['lot_number']);
            $table->index(['serial_number']);
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('type', 24)->default('cycle'); // cycle|full
            $table->string('status', 24)->default('open');
            $table->foreignId('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('expected');
            $table->integer('counted')->nullable();
            $table->integer('variance')->nullable();
            $table->timestampsTz();
        });

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('quantity'); // signed
            $table->string('reason', 120);
            $table->string('status', 24)->default('pending'); // pending|approved|applied
            $table->foreignId('maker_id')->nullable();
            $table->foreignId('checker_id')->nullable();
            $table->timestampsTz();
        });

        Schema::create('inventory_thresholds', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('warning_level')->nullable();
            $table->integer('reorder_level')->nullable();
            $table->timestampsTz();

            $table->unique('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_thresholds');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('lot_serials');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
    }
};
