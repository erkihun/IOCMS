<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CAT module tables (LLD §5.6 / Appendix A). Category tree (no cycles —
 * FR-CAT-002), brands, attributes/values/sets, units, taxonomy change events
 * (FR-CAT-001..010). Localized fields live in *_translations (FR-LOC-002).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug', 191)->unique();
            $table->string('status', 24)->default('active'); // active|retired
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('path', 500)->nullable();  // materialized path for cycle checks
            $table->timestampsTz();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('category_translations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('meta_title', 191)->nullable();
            $table->string('meta_description', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['category_id', 'locale']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('slug', 191)->unique();
            $table->string('status', 24)->default('active');
            $table->string('logo_path', 255)->nullable();
            $table->timestampsTz();
        });

        Schema::create('brand_translations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->unique(['brand_id', 'locale']);
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('code', 80)->unique();
            $table->string('data_type', 24);  // text|number|boolean|date|measurement|select|multiselect
            $table->boolean('is_variant_defining')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_comparable')->default(false);
            $table->string('unit_code', 24)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        Schema::create('attribute_translations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label', 191);
            $table->timestampsTz();

            $table->unique(['attribute_id', 'locale']);
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('normalized_value', 191)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(['attribute_id', 'code']);
        });

        Schema::create('attribute_value_translations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('attribute_value_id')->constrained('attribute_values')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label', 191);
            $table->timestampsTz();

            $table->unique(['attribute_value_id', 'locale']);
        });

        Schema::create('attribute_sets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('code', 80)->unique();
            $table->string('name', 191);
            $table->timestampsTz();
        });

        Schema::create('attribute_set_attributes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('attribute_set_id')->constrained('attribute_sets')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['attribute_set_id', 'attribute_id']);
        });

        Schema::create('category_attribute_sets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('attribute_set_id')->constrained('attribute_sets')->cascadeOnDelete();

            $table->unique(['category_id', 'attribute_set_id']);
        });

        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 24)->unique();
            $table->string('name', 80);
            $table->string('dimension', 40)->nullable(); // length|mass|volume
            $table->decimal('base_factor', 20, 6)->default(1);
            $table->timestampsTz();
        });

        Schema::create('taxonomy_change_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('change', 60);
            $table->json('detail')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->timestampTz('created_at');

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_change_events');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('category_attribute_sets');
        Schema::dropIfExists('attribute_set_attributes');
        Schema::dropIfExists('attribute_sets');
        Schema::dropIfExists('attribute_value_translations');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_translations');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('brand_translations');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('categories');
    }
};
