<?php

namespace App\Http\Controllers;

use App\Models\VehicleType;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public taxi request foundation (12A.1).
 *
 * Deliberate decision: public requests create a CRM Lead
 * (service_type=taxi), never a booking directly — pricing and fleet
 * assignment stay staff-side until the pricing engine ships. Staff
 * convert the lead/quotation into a TaxiBooking from the desk.
 */
class TaxiEnquiryController extends Controller
{
    public function __construct(protected LeadService $leads) {}

    public function show(): Response
    {
        return Inertia::render('Taxi/Enquiry', [
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trip_type' => ['required', 'in:one_way,airport_transfer'],
            'pickup_address' => ['required', 'string', 'max:500'],
            'drop_address' => ['required', 'string', 'max:500'],
            'pickup_at' => ['required', 'date', 'after:now'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:60'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $vehicleType = isset($validated['vehicle_type_id'])
            ? VehicleType::find($validated['vehicle_type_id'])
            : null;

        $summary = implode("\n", array_filter([
            'Trip: '.($validated['trip_type'] === 'airport_transfer' ? 'Airport Transfer' : 'One Way'),
            'Pickup: '.$validated['pickup_address'].' at '.$validated['pickup_at'],
            'Drop: '.$validated['drop_address'],
            'Passengers: '.$validated['passenger_count'],
            $vehicleType ? 'Vehicle: '.$vehicleType->name : null,
            ! empty($validated['notes']) ? 'Notes: '.$validated['notes'] : null,
        ]));

        $this->leads->createLead([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'service_type' => 'taxi',
            'product_title' => $vehicleType?->name,
            'destination' => mb_substr($validated['drop_address'], 0, 150),
            'adults' => $validated['passenger_count'],
            'summary' => $summary,
        ]);

        return back()->with('flash', 'Taxi request received. Our team will confirm your ride shortly.');
    }
}
