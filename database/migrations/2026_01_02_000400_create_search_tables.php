<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRC module tables (LLD §5.8). Synonyms, merchandising rules, query/click
 * analytics, recommendation exclusions, index versions (FR-SRC-001..010). The
 * search projection is derived and rebuildable; authoritative eligibility is
 * revalidated at checkout (ADR-014).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_synonym_sets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('locale', 5);
            $table->string('name', 120);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        Schema::create('search_synonyms', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('search_synonym_set_id')->constrained('search_synonym_sets')->cascadeOnDelete();
            $table->string('term', 120);
            $table->string('equivalent', 120);
            $table->timestampsTz();

            $table->index(['term']);
        });

        Schema::create('search_merchandising_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('query_pattern', 191)->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('action', 24); // pin|boost|bury|suppress
            $table->foreignId('product_id')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'priority']);
        });

        Schema::create('search_query_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('query_hash', 64);
            $table->string('locale', 5);
            $table->unsignedInteger('result_count');
            $table->boolean('zero_result')->default(false);
            $table->timestampTz('created_at');

            $table->index(['zero_result', 'created_at']);
        });

        Schema::create('search_click_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('query_hash', 64);
            $table->foreignId('product_id')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->boolean('converted')->default(false);
            $table->timestampTz('created_at');
        });

        Schema::create('recommendation_exclusions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('reason', 120)->nullable();
            $table->timestampsTz();
        });

        Schema::create('search_index_versions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('index_name', 80);
            $table->unsignedBigInteger('version');
            $table->string('status', 24)->default('building'); // building|active|stale
            $table->timestamp('built_at')->nullable();
            $table->timestampsTz();

            $table->index(['index_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index_versions');
        Schema::dropIfExists('recommendation_exclusions');
        Schema::dropIfExists('search_click_events');
        Schema::dropIfExists('search_query_logs');
        Schema::dropIfExists('search_merchandising_rules');
        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('search_synonym_sets');
    }
};
