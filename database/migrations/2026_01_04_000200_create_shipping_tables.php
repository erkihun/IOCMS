<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SHP module tables (LLD §5.16). Delivery zones/methods/rates, shipments,
 * tracking (deduplicated events), delivery assignments, proof of delivery,
 * manifests (FR-SHP-001..014, BR-010 quantity ladder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('name', 120);
            $table->json('coverage')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        Schema::create('shipping_zone_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('match_type', 24); // region|city|postal|geo
            $table->string('match_value', 191);
            $table->timestampsTz();
        });

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('type', 24)->default('standard'); // standard|express|pickup
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->decimal('base_amount', 20, 4);
            $table->decimal('per_kg_amount', 20, 4)->default(0);
            $table->char('currency', 3)->default('ETB');
            $table->timestampsTz();

            $table->unique(['shipping_method_id', 'shipping_zone_id']);
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('shipment_number', 40)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 30)->default('pending'); // ShipmentStatus
            $table->string('provider', 60)->nullable();
            $table->string('tracking_reference', 120)->nullable();
            $table->foreignId('shipping_method_id')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->index(['order_id', 'status']);
        });

        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestampsTz();
        });

        Schema::create('shipment_packages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('package_reference', 60);
            $table->timestampsTz();
        });

        Schema::create('tracking_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('external_event_id', 191)->nullable();
            $table->string('status', 40);
            $table->string('description', 255)->nullable();
            $table->string('location', 191)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestampTz('created_at');

            // FR-SHP-008: dedupe duplicate tracking events per shipment.
            $table->unique(['shipment_id', 'external_event_id']);
        });

        Schema::create('delivery_assignments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable();
            $table->string('status', 24)->default('assigned');
            $table->timestampsTz();
        });

        Schema::create('proof_of_deliveries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('recipient_name', 191)->nullable();
            $table->string('proof_type', 24); // signature|photo|otp
            $table->string('proof_reference', 255)->nullable();
            $table->timestamp('delivered_at');
            $table->timestampsTz();
        });

        Schema::create('delivery_attempts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('outcome', 24); // delivered|failed
            $table->string('reason', 191)->nullable();
            $table->timestampTz('created_at');

            $table->unique(['shipment_id', 'attempt_no']);
        });

        Schema::create('manifests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('manifest_number', 40)->unique();
            $table->string('provider', 60)->nullable();
            $table->string('status', 24)->default('open');
            $table->timestampsTz();
        });

        Schema::create('manifest_shipments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('manifest_id')->constrained('manifests')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();

            $table->unique(['manifest_id', 'shipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_shipments');
        Schema::dropIfExists('manifests');
        Schema::dropIfExists('delivery_attempts');
        Schema::dropIfExists('proof_of_deliveries');
        Schema::dropIfExists('delivery_assignments');
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('shipment_packages');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zone_rules');
        Schema::dropIfExists('shipping_zones');
    }
};
