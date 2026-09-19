<?php

use App\Models\NumberSeries;
use App\Services\NumberSeriesService;
use App\Support\StaffPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Driver earnings number series + finance permissions (12A.10).
     *
     * Idempotent: existing counters are never reset, permissions are
     * append-only. Privileged staff roles gain the new keys; narrower
     * roles are extended through the Roles UI or defaultRoles seeds.
     */
    public function up(): void
    {
        if (Schema::hasTable('number_series')) {
            foreach (NumberSeriesService::definitions() as $entity => $definition) {
                if (! in_array($entity, ['taxi_driver_earning', 'taxi_driver_payout'], true)) {
                    continue;
                }

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

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $finance = [
            'taxi.driver_earnings.view',
            'taxi.driver_earnings.manage',
            'taxi.driver_payouts.view',
            'taxi.driver_payouts.manage',
        ];

        foreach ($finance as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::whereIn('name', ['super-admin', 'administrator'])->get() as $role) {
            $current = $role->permissions()->pluck('name')->all();
            $valid = array_values(array_intersect(
                array_unique(array_merge($current, $finance)),
                StaffPermissions::all()
            ));
            $role->syncPermissions($valid);
        }
    }

    public function down(): void
    {
        // Series rows and permissions are append-only by design.
    }
};
