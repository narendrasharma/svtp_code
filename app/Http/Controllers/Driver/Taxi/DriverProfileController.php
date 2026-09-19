<?php

namespace App\Http\Controllers\Driver\Taxi;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver Portal profile (Phase 12A.4).
 *
 * Read-only for everything administrative (vendor ownership, approval,
 * compliance, permissions). Self-service is limited to contact fields.
 * Availability accepts only driver-safe states — on_leave and any
 * system-derived state stay vendor/admin-owned.
 */
class DriverProfileController extends DriverPortalController
{
    /**
     * States a driver may set for themselves. on_leave is owned by
     * vendor/admin availability records; nothing system-derived is
     * exposed here.
     *
     * @return array<int, string>
     */
    public static function allowedAvailability(): array
    {
        return ['available', 'offline'];
    }

    public function show(Request $request): Response
    {
        $driver = $this->driver($request);

        $driver->load(['vendorProfile:id,business_name', 'documents' => fn ($q) => $q->latest()->limit(10)]);

        return Inertia::render('Driver/Taxi/Profile', [
            'driver' => $driver,
            'availabilityOptions' => [
                ['value' => 'available', 'label' => 'Available'],
                ['value' => 'offline', 'label' => 'Off Duty'],
            ],
            'leave' => $driver->availabilities()
                ->whereIn('status', ['unavailable', 'on_leave'])
                ->orderBy('from_at')->limit(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $driver = $this->driver($request);

        $validated = $request->validate([
            'phone' => ['sometimes', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $driver->update($validated);

        return back()->with('flash', 'Profile updated.');
    }

    public function availability(Request $request): RedirectResponse
    {
        $driver = $this->driver($request);

        $validated = $request->validate([
            'availability_status' => ['required', Rule::in(self::allowedAvailability())],
        ]);

        $driver->update(['availability_status' => $validated['availability_status']]);

        return back()->with('flash', 'Availability updated.');
    }
}
