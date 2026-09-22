<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['hotel.cancellations.view', 'hotel.cancellations.manage', 'hotel.refunds.view', 'hotel.refunds.manage', 'hotel.reschedules.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['hotel.cancellations.view', 'hotel.cancellations.manage', 'hotel.refunds.view', 'hotel.refunds.manage', 'hotel.reschedules.manage'])->delete();
    }
};
