<?php

namespace App\Http\Controllers\Driver\Taxi;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TaxiBooking;
use Illuminate\Http\Request;

/**
 * Driver Portal base (Phase 12A.4).
 *
 * Identity always resolves from the authenticated user through
 * drivers.user_id — never from request input. Trip access additionally
 * requires an OPEN assignment row for that driver; anything else is a
 * 404 per project convention (existence is not leaked).
 */
abstract class DriverPortalController extends Controller
{
    protected function driver(Request $request): Driver
    {
        $user = $request->user();

        $driver = $user ? Driver::where('user_id', $user->id)->first() : null;

        abort_unless($driver && $driver->is_active && $driver->employment_status === 'active', 403, 'Driver access only.');

        return $driver;
    }

    protected function assignedTrip(Request $request, TaxiBooking $taxiBooking): TaxiBooking
    {
        $driver = $this->driver($request);

        $open = $taxiBooking->assignments()->open()->where('driver_id', $driver->id)->exists();

        abort_unless($open, 404);

        return $taxiBooking;
    }

    /**
     * Strip commercial internals before serializing a booking to the
     * driver portal. The driver sees operational fields only.
     *
     * @return array<int, string>
     */
    public static function hiddenFinancialAttributes(): array
    {
        return [
            'base_amount', 'extra_amount', 'discount_amount', 'tax_amount', 'total_amount',
            'pricing_snapshot', 'priced_at', 'taxi_rate_card_id', 'taxi_rental_package_id',
        ];
    }

    protected function hideFinancials(TaxiBooking $taxiBooking): TaxiBooking
    {
        return $taxiBooking->makeHidden(self::hiddenFinancialAttributes());
    }
}
