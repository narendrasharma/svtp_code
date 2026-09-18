<?php

use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed the campaign series and refresh the support-ticket display
     * copy now that tickets are a real domain. Idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('number_series')) {
            return;
        }

        foreach (NumberSeriesService::definitions() as $entity => $definition) {
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

            if (in_array($entity, ['support_ticket', 'campaign'], true)) {
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
