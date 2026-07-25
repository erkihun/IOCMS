<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CUS module tables (LLD §5.4). Customer profile, addresses (Ethiopian admin
 * fields + geolocation — FR-CUS-004), consent records (BR-041), wishlists,
 * guest-order links (FR-CUS-006). Restricted PII is encrypted at rest (NFR-SE-015).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->timestampsTz();
        });

        Schema::create('addresses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('recipient_name', 191);
            $table->string('recipient_phone', 32);
            $table->string('region', 120)->nullable();     // Ethiopian admin fields
            $table->string('zone', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('subcity', 120)->nullable();
            $table->string('woreda', 120)->nullable();
            $table->string('kebele', 120)->nullable();
            $table->text('landmark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default_delivery')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('user_id');
        });

        Schema::create('consent_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 60);          // marketing_email|marketing_sms|...
            $table->boolean('granted');
            $table->string('policy_version', 40)->nullable();
            $table->string('source', 40)->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->index(['user_id', 'purpose']);
        });

        Schema::create('wishlists', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120)->default('Default');
            $table->timestampsTz();
        });

        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('wishlist_id')->constrained('wishlists')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['wishlist_id', 'product_variant_id']);
        });

        Schema::create('customer_activity_links', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('activity_type', 40); // guest_order_link|...
            $table->string('reference', 60);
            $table->timestampTz('created_at');

            $table->index(['user_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activity_links');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customer_profiles');
    }
};
