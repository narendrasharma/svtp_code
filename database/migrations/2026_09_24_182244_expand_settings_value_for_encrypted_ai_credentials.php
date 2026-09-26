<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->text('value')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('settings')->whereRaw('LENGTH(value) > 255')->exists()) {
            throw new RuntimeException('Cannot shrink settings.value while encrypted credentials exceed 255 characters.');
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->string('value')->nullable()->change();
        });
    }
};
