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
        Schema::table('taxi_bookings', static function (Blueprint $table) {
            $table->foreignId('taxi_rate_card_id')->nullable()->after('vehicle_type_id')->constrained()->nullOnDelete();
            $table->foreignId('taxi_rental_package_id')->nullable()->after('taxi_rate_card_id')->constrained()->nullOnDelete();
            $table->dateTime('return_at')->nullable()->after('pickup_at');
            $table->json('pricing_snapshot')->nullable()->after('quoted_duration_minutes');
            $table->dateTime('priced_at')->nullable()->after('pricing_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxi_bookings', static function (Blueprint $table) {
            $table->dropForeign(['taxi_rate_card_id']);
            $table->dropForeign(['taxi_rental_package_id']);
            $table->dropColumn([
                'taxi_rate_card_id',
                'taxi_rental_package_id',
                'return_at',
                'pricing_snapshot',
                'priced_at',
            ]);
        });
    }
};
