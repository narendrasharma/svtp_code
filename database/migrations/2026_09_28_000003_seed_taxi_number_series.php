<?php

use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed taxi number series (taxi_ride display refresh + driver and
     * vehicle counters). Idempotent: existing counters are never reset.
     */
    public function up(): void
    {
        if (! Schema::hasTable('number_series')) {
            return;
        }

        foreach (NumberSeriesService::definitions() as $entity => $definition) {
            if (! in_array($entity, ['taxi_ride', 'taxi_driver', 'taxi_vehicle'], true)) {
                continue;
            }

            $row = NumberSeries::firstOrCreate(
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

            if ($entity === 'taxi_ride') {
                $row->update([
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                ]);
            }
        }
    }

    public function down(): void
    {
        // Series rows are shared counters — never removed on rollback.
    }
};
