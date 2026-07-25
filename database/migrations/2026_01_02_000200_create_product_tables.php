<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD module tables (LLD §5.7). Product master separate from purchasable
 * variants (BR-001), lifecycle states (FR-PRD-009), media, relations, bundles,
 * policy metadata, change history (FR-PRD-014).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('attribute_set_id')->nullable()->constrained('attribute_sets')->nullOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable(); // marketplace ownership (gated)
            $table->string('status', 24)->default('draft'); // draft|pending_review|approved|published|suspended|archived|rejected
            $table->string('type', 24)->default('standard'); // standard|bundle
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->index(['status', 'category_id']);
            $table->index('seller_id');
        });

        Schema::create('product_translations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('specifications')->nullable();
            $table->longText('care_instructions')->nullable();
            $table->timestampsTz();

            $table->unique(['product_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 80);
            $table->string('barcode', 80)->nullable();
            $table->string('status', 24)->default('active');
            $table->decimal('weight_grams', 20, 6)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestampsTz();

            // FR-PRD-003: active SKU/barcode unique within scope (merchant/seller).
            $table->unique(['sku']);
            $table->index('barcode');
        });

        Schema::create('variant_attribute_values', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained('attribute_values')->nullOnDelete();
            $table->string('raw_value', 191)->nullable();

            $table->unique(['product_variant_id', 'attribute_id']);
        });

        Schema::create('product_identifiers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('type', 24); // supplier_ref|mpn|gtin
            $table->string('value', 120);
            $table->timestampsTz();

            $table->index(['type', 'value']);
        });

        Schema::create('product_media', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('type', 16)->default('image'); // image|video|document
            $table->string('disk', 40)->default('local');
            $table->string('path', 255);
            $table->char('checksum', 64)->nullable();
            $table->string('scan_status', 24)->default('pending'); // pending|clean|infected
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_relations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 24); // related|alternative|accessory|cross_sell
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['product_id', 'related_product_id', 'type']);
        });

        Schema::create('product_bundles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('pricing_mode', 24)->default('sum'); // sum|fixed
            $table->timestampsTz();
        });

        Schema::create('product_bundle_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);

            $table->unique(['product_bundle_id', 'product_variant_id']);
        });

        Schema::create('product_policy_metadata', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->unsignedInteger('return_window_days')->nullable();
            $table->boolean('is_returnable')->default(true);
            $table->boolean('is_age_restricted')->default(false);
            $table->boolean('is_restricted')->default(false);
            $table->string('tax_class', 40)->default('standard');
            $table->unsignedInteger('policy_version')->default(1);
            $table->timestampsTz();

            $table->unique('product_id');
        });

        Schema::create('product_change_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('field', 80);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->timestampTz('created_at');

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_change_events');
        Schema::dropIfExists('product_policy_metadata');
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_identifiers');
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
    }
};
