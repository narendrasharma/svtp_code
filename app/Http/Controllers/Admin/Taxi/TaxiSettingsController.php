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
            'taxi_default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'taxi_min_advance_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'taxi_max_advance_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'taxi_allow_guest_booking' => ['required', 'boolean'],
            'taxi_default_assignment_mode' => ['required', Rule::in(['manual'])],
            'taxi_tracking_stale_seconds' => ['required', 'integer', 'min:30', 'max:3600'],
            'taxi_location_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'taxi_maps_provider' => ['required', Rule::in(['none', 'google', 'mapbox'])],
            'taxi_maps_enabled' => ['required', 'boolean'],
            'taxi_maps_google_browser_key' => ['nullable', 'string', 'max:255'],
            'taxi_maps_google_server_key' => ['nullable', 'string', 'max:255'],
            'taxi_maps_mapbox_public_token' => ['nullable', 'string', 'max:255'],
            'taxi_maps_mapbox_server_token' => ['nullable', 'string', 'max:255'],
            'taxi_routing_enabled' => ['required', 'boolean'],
            'taxi_routing_cache_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'taxi_routing_refresh_seconds' => ['required', 'integer', 'min:30', 'max:300'],
            'taxi_dispatch_smart_enabled' => ['required', 'boolean'],
            'taxi_dispatch_max_pickup_radius_km' => ['required', 'numeric', 'min:0', 'max:500'],
            'taxi_dispatch_routing_candidate_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'taxi_dispatch_use_routing_eta' => ['required', 'boolean'],
            'taxi_dispatch_auto_enabled' => ['required', 'boolean'],
            'taxi_dispatch_offer_enabled' => ['required', 'boolean'],
            'taxi_dispatch_offer_timeout_seconds' => ['required', 'integer', 'min:30', 'max:600'],
            'taxi_dispatch_max_offer_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'taxi_dispatch_require_driver_acceptance' => ['required', 'boolean'],
            'taxi_dispatch_auto_fallback_manual' => ['required', 'boolean'],
            'taxi_customer_tracking_enabled' => ['required', 'boolean'],
            'taxi_customer_tracking_token_expiry_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'taxi_customer_tracking_show_driver_phone' => ['required', 'boolean'],
            'taxi_customer_tracking_show_vehicle_registration' => ['required', 'boolean'],
            'taxi_customer_tracking_show_route' => ['required', 'boolean'],
            'taxi_customer_tracking_refresh_seconds' => ['required', 'integer', 'min:15', 'max:60'],
        ]);

        Setting::setValue('taxi.booking_enabled', $validated['taxi_booking_enabled'] ? '1' : '0');
        Setting::setValue('taxi.one_way_enabled', $validated['taxi_one_way_enabled'] ? '1' : '0');
        Setting::setValue('taxi.airport_transfer_enabled', $validated['taxi_airport_transfer_enabled'] ? '1' : '0');
        Setting::setValue('taxi.default_currency', strtoupper($validated['taxi_default_currency']));
        Setting::setValue('taxi.min_advance_minutes', (string) $validated['taxi_min_advance_minutes']);
        Setting::setValue('taxi.max_advance_days', $validated['taxi_max_advance_days'] !== null ? (string) $validated['taxi_max_advance_days'] : '');
        Setting::setValue('taxi.allow_guest_booking', $validated['taxi_allow_guest_booking'] ? '1' : '0');
        Setting::setValue('taxi.default_assignment_mode', $validated['taxi_default_assignment_mode']);
        Setting::setValue('taxi.tracking_stale_seconds', (string) $validated['taxi_tracking_stale_seconds']);
        Setting::setValue('taxi.location_retention_days', (string) $validated['taxi_location_retention_days']);
        Setting::setValue('taxi.maps.provider', $validated['taxi_maps_provider']);
        Setting::setValue('taxi.maps.enabled', $validated['taxi_maps_enabled'] ? '1' : '0');
        Setting::setValue('taxi.maps.google.browser_key', trim((string) ($validated['taxi_maps_google_browser_key'] ?? '')));
        Setting::setValue('taxi.maps.google.server_key', trim((string) ($validated['taxi_maps_google_server_key'] ?? '')));
        Setting::setValue('taxi.maps.mapbox.public_token', trim((string) ($validated['taxi_maps_mapbox_public_token'] ?? '')));
        Setting::setValue('taxi.maps.mapbox.server_token', trim((string) ($validated['taxi_maps_mapbox_server_token'] ?? '')));
        Setting::setValue('taxi.routing.enabled', $validated['taxi_routing_enabled'] ? '1' : '0');
        Setting::setValue('taxi.routing.cache_minutes', (string) $validated['taxi_routing_cache_minutes']);
        Setting::setValue('taxi.routing.refresh_seconds', (string) $validated['taxi_routing_refresh_seconds']);
        Setting::setValue('taxi.dispatch.smart_enabled', $validated['taxi_dispatch_smart_enabled'] ? '1' : '0');
        Setting::setValue('taxi.dispatch.max_pickup_radius_km', (string) $validated['taxi_dispatch_max_pickup_radius_km']);
        Setting::setValue('taxi.dispatch.routing_candidate_limit', (string) $validated['taxi_dispatch_routing_candidate_limit']);
        Setting::setValue('taxi.dispatch.use_routing_eta', $validated['taxi_dispatch_use_routing_eta'] ? '1' : '0');
        Setting::setValue('taxi.dispatch.auto_enabled', $validated['taxi_dispatch_auto_enabled'] ? '1' : '0');
        Setting::setValue('taxi.dispatch.offer_enabled', $validated['taxi_dispatch_offer_enabled'] ? '1' : '0');
        Setting::setValue('taxi.dispatch.offer_timeout_seconds', (string) $validated['taxi_dispatch_offer_timeout_seconds']);
        Setting::setValue('taxi.dispatch.max_offer_attempts', (string) $validated['taxi_dispatch_max_offer_attempts']);
        Setting::setValue('taxi.dispatch.require_driver_acceptance', $validated['taxi_dispatch_require_driver_acceptance'] ? '1' : '0');
        Setting::setValue('taxi.dispatch.auto_fallback_manual', $validated['taxi_dispatch_auto_fallback_manual'] ? '1' : '0');
        Setting::setValue('taxi.customer_tracking.enabled', $validated['taxi_customer_tracking_enabled'] ? '1' : '0');
        Setting::setValue('taxi.customer_tracking.token_expiry_hours', $validated['taxi_customer_tracking_token_expiry_hours'] !== null ? (string) $validated['taxi_customer_tracking_token_expiry_hours'] : '');
        Setting::setValue('taxi.customer_tracking.show_driver_phone', $validated['taxi_customer_tracking_show_driver_phone'] ? '1' : '0');
        Setting::setValue('taxi.customer_tracking.show_vehicle_registration', $validated['taxi_customer_tracking_show_vehicle_registration'] ? '1' : '0');
        Setting::setValue('taxi.customer_tracking.show_route', $validated['taxi_customer_tracking_show_route'] ? '1' : '0');
        Setting::setValue('taxi.customer_tracking.refresh_seconds', (string) $validated['taxi_customer_tracking_refresh_seconds']);

        return back()->with('flash', 'Taxi settings updated.');
    }
}
