<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TaxiBooking;
use App\Models\Vehicle;
use App\Models\VendorProfile;
use App\Services\TaxiAvailabilityService;
use App\Services\TaxiBookingService;
use App\Services\TaxiDispatchRecommendationService;
use App\Services\TaxiDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 12A.3 manual dispatch board (platform-wide).
 *
 * Board + metrics + eligible fleet + internal notes. Assignment and
 * ride-status mutations stay on TaxiBookingController endpoints and
 * TaxiBookingService rules — this controller never duplicates them.
 */
class TaxiDispatchController extends Controller
{
    public function __construct(
        protected TaxiDispatchService $dispatch,
        protected TaxiAvailabilityService $availability,
        protected TaxiBookingService $bookings,
        protected TaxiDispatchRecommendationService $recommendations,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search', 'bucket', 'status', 'time_view',
            'vendor_id', 'driver_id', 'vehicle_id',
            'date_from', 'date_to',
        ]);

        $board = $this->dispatch->boardQuery($filters)->paginate(15)->withQueryString();

        $board->getCollection()->transform(function (TaxiBooking $booking): TaxiBooking {
            $booking->setAttribute('allowed_transitions', collect($booking->status()->allowedTransitions())
                ->map(fn (TaxiBookingStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values());

            $summary = $this->bookings->summary($booking);
            $booking->setAttribute('payment_summary', $summary);

            return $booking;
        });

        return Inertia::render('Admin/Taxi/Dispatch/Index', [
            'board' => $board,
            'filters' => $filters,
            'metrics' => $this->dispatch->metrics(),
            'bucketCounts' => $this->dispatch->bucketCounts(),
            'buckets' => TaxiDispatchService::bucketOptions(),
            'timeViews' => TaxiDispatchService::timeViewOptions(),
            'statuses' => collect(TaxiBookingStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'drivers' => Driver::where('is_active', true)->orderBy('first_name')->limit(200)->get(['id', 'first_name', 'last_name', 'vendor_profile_id']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name', 'registration_number', 'vendor_profile_id']),
        ]);
    }

    /**
     * Eligible fleet for a booking (manual desk selection).
     */
    public function eligible(TaxiBooking $taxiBooking): JsonResponse
    {
        $drivers = $this->availability->eligibleDrivers($taxiBooking->load('vendorProfile'))->map(fn (array $row): array => [
            'id' => $row['driver']->id,
            'name' => $row['driver']->fullName(),
            'availability_status' => $row['driver']->availability_status,
            'employment_status' => $row['driver']->employment_status,
            'eligible' => $row['eligible'],
            'reason' => $row['reason'],
        ])->values();

        $vehicles = $this->availability->eligibleVehicles($taxiBooking)->map(fn (array $row): array => [
            'id' => $row['vehicle']->id,
            'name' => $row['vehicle']->name,
            'registration_number' => $row['vehicle']->registration_number,
            'status' => $row['vehicle']->status,
            'passenger_capacity' => $row['vehicle']->passenger_capacity,
            'eligible' => $row['eligible'],
            'reason' => $row['reason'],
        ])->values();

        return response()->json([
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'allowed_transitions' => collect($taxiBooking->status()->allowedTransitions())
                ->map(fn (TaxiBookingStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
        ]);
    }

    public function storeNote(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $this->dispatch->addNote($taxiBooking, $request->user(), $validated['body']);

        return back()->with('flash', 'Operational note added.');
    }

    public function notes(TaxiBooking $taxiBooking): JsonResponse
    {
        $taxiBooking->load(['notes.author:id,name']);

        return response()->json([
            'notes' => $taxiBooking->notes->map(fn ($note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author?->name,
                'created_at' => $note->created_at,
            ]),
        ]);
    }

    /**
     * Smart dispatch recommendations (12A.7, decision support only).
     * The payload never authorizes anything — assignment revalidates.
     */
    public function recommendations(TaxiBooking $taxiBooking): JsonResponse
    {
        return response()->json($this->recommendations->recommend($taxiBooking));
    }
}
