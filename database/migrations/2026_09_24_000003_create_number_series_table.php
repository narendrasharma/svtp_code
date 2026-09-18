<?php

use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Global number/reference series (Phase 11.5A). One row per entity;
 * counters move forward only. Changing settings affects FUTURE
 * references — historical rows are never renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_series', static function (Blueprint $table) {
            $table->id();
            $table->string('entity')->unique();
            $table->string('display_name');
            $table->string('prefix', 10);
            $table->string('separator', 5)->default('-');
            $table->boolean('include_year')->default(false);
            $table->boolean('include_month')->default(false);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->unsignedBigInteger('start_number')->default(1);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset_cycle', 10)->default('never');
            $table->string('last_period', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        foreach (NumberSeriesService::definitions() as $entity => $definition) {
            NumberSeries::firstOrCreate(
                ['entity' => $entity],
                [
                    'display_name' => $definition['display_name'],
                    'prefix' => $definition['prefix'],
                    'separator' => $definition['separator'],
                    'include_year' => $definition['include_year'],
                    'include_month' => $definition['include_month'],
                    'padding' => $definition['padding'],
                    'start_number' => $definition['start_number'],
                    'next_number' => $definition['start_number'],
                    'reset_cycle' => $definition['reset_cycle'],
                    'last_period' => null,
                    'is_active' => true,
                    'description' => $definition['description'],
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('number_series');
    }
};
