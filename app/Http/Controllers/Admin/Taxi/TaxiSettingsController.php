<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\TaxiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taxi settings foundation (12A.1). Small deliberate list — trip
 * availability, advance window, guest booking, assignment mode.
 * Pricing rules stay in their own future domain.
 */
class TaxiSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Taxi/Settings', [
            'settings' => TaxiSettings::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'taxi_booking_enabled' => ['required', 'boolean'],
            'taxi_one_way_enabled' => ['required', 'boolean'],
            'taxi_airport_transfer_enabled' => ['required', 'boolean'],
            'taxi_default_currency' => ['required', Rule::in(['INR', 'USD', 'EUR', 'AED'])],
            'taxi_min_advance_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'taxi_max_advance_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'taxi_allow_guest_booking' => ['required', 'boolean'],
            'taxi_default_assignment_mode' => ['required', Rule::in(['manual'])],
        ]);

        Setting::setValue('taxi.booking_enabled', $validated['taxi_booking_enabled'] ? '1' : '0');
        Setting::setValue('taxi.one_way_enabled', $validated['taxi_one_way_enabled'] ? '1' : '0');
        Setting::setValue('taxi.airport_transfer_enabled', $validated['taxi_airport_transfer_enabled'] ? '1' : '0');
        Setting::setValue('taxi.default_currency', $validated['taxi_default_currency']);
        Setting::setValue('taxi.min_advance_minutes', (string) $validated['taxi_min_advance_minutes']);
        Setting::setValue('taxi.max_advance_days', $validated['taxi_max_advance_days'] !== null ? (string) $validated['taxi_max_advance_days'] : '');
        Setting::setValue('taxi.allow_guest_booking', $validated['taxi_allow_guest_booking'] ? '1' : '0');
        Setting::setValue('taxi.default_assignment_mode', $validated['taxi_default_assignment_mode']);

        return back()->with('flash', 'Taxi settings updated.');
    }
}
