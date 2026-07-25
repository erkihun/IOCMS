<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOC module tables (LLD §5.5 / Appendix A). Locales, translation bundles,
 * currency configuration, exchange rates, calendar display rules
 * (FR-LOC-001..008, BR-045). Translatable content is stored independently from
 * the source business record (FR-LOC-002).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 5)->unique();     // en, am
            $table->string('name', 80);
            $table->string('native_name', 80);
            $table->string('calendar_system', 20)->default('gregorian');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('translation_bundles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->string('namespace', 80);         // e.g. errors, ui, email
            $table->string('locale', 5);
            $table->string('status', 24)->default('draft'); // draft|review|published
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->unique(['namespace', 'locale', 'version']);
        });

        Schema::create('translation_entries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('translation_bundle_id')->constrained('translation_bundles')->cascadeOnDelete();
            $table->string('key', 191);
            $table->text('value');
            $table->string('status', 24)->default('draft'); // draft|reviewed
            $table->timestampsTz();

            $table->unique(['translation_bundle_id', 'key']);
        });

        Schema::create('currency_configs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('code', 3)->unique();       // ETB
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('display_scale')->default(2);
            $table->string('rounding_mode', 16)->default('half_up');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 20, 6);
            $table->timestamp('effective_at');
            $table->foreignId('imported_by')->nullable();
            $table->timestampsTz();

            $table->index(['base_currency', 'quote_currency', 'effective_at']);
        });

        Schema::create('calendar_display_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('locale', 5);
            $table->string('calendar_system', 20); // ethiopian|gregorian
            $table->string('date_format', 40)->default('yyyy-MM-dd');
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();

            $table->unique(['locale', 'calendar_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_display_rules');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currency_configs');
        Schema::dropIfExists('translation_entries');
        Schema::dropIfExists('translation_bundles');
        Schema::dropIfExists('locales');
    }
};
