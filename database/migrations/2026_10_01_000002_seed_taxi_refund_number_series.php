<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('number_series')->insertOrIgnore([
            'entity' => 'taxi_refund', 'display_name' => 'Taxi Refund', 'prefix' => 'TXR',
            'separator' => '-', 'include_year' => true, 'include_month' => false,
            'padding' => 6, 'start_number' => 1, 'next_number' => 1,
            'reset_cycle' => 'yearly', 'last_period' => null, 'is_active' => true,
            'description' => 'Manual taxi refund references.', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};
