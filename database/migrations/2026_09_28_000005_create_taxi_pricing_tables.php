<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('taxi_rate_cards', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('trip_type', 30);
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['vendor_profile_id', 'vehicle_type_id', 'trip_type', 'currency', 'is_active'],
                'taxi_rate_cards_resolution_index'
            );
            $table->index(['effective_from', 'effective_until']);
        });

        Schema::create('taxi_rate_rules', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_rate_card_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('calculation_type', 20);
            $table->decimal('amount', 12, 4)->default(0);
            $table->decimal('included_quantity', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['taxi_rate_card_id', 'code']);
        });

        Schema::create('taxi_rental_packages', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_rate_card_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('included_hours', 8, 2);
            $table->decimal('included_km', 10, 2)->default(0);
            $table->decimal('package_price', 12, 2);
            $table->decimal('extra_km_rate', 12, 4)->default(0);
            $table->decimal('extra_hour_rate', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['taxi_rate_card_id', 'is_active', 'sort_order'], 'taxi_rental_packages_card_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxi_rental_packages');
        Schema::dropIfExists('taxi_rate_rules');
        Schema::dropIfExists('taxi_rate_cards');
    }
};
