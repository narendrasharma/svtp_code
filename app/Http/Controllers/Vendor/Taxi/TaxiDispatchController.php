<?php

namespace App\Http\Controllers\Vendor\Taxi;

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
 * Phase 12A.3 vendor dispatch board (own bookings/fleet only).
 * Cross-vendor access returns 404 per project convention.
 */
class TaxiDispatchController extends Controller
{
    public function __construct(
        protected TaxiDispatchService $dispatch,
        protected TaxiAvailabilityService $availability,
        protected TaxiBookingService $bookings,
        protected TaxiDispatchRecommendationService $recommendations,
    ) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scoped(Request $request, TaxiBooking $taxiBooking): TaxiBooking
    {
        abort_unless((int) $taxiBooking->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $taxiBooking;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $filters = $request->only([
            'search', 'bucket', 'status', 'time_view',
            'driver_id', 'vehicle_id', 'date_from', 'date_to',
        ]);

        $board = $this->dispatch->boardQuery($filters, $profile->id)->paginate(15)->withQueryString();

        $board->getCollection()->transform(function (TaxiBooking $booking): TaxiBooking {
            $booking->setAttribute('allowed_transitions', collect($booking->status()->allowedTransitions())
                ->map(fn (TaxiBookingStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values());

            $booking->setAttribute('payment_summary', $this->bookings->summary($booking));

            return $booking;
        });

        return Inertia::render('Vendor/Taxi/Dispatch/Index', [
            'board' => $board,
            'filters' => $filters,
            'metrics' => $this->dispatch->metrics($profile->id),
            'bucketCounts' => $this->dispatch->bucketCounts($profile->id),
            'buckets' => TaxiDispatchService::bucketOptions(),
            'timeViews' => TaxiDispatchService::timeViewOptions(),
            'statuses' => collect(TaxiBookingStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
            'drivers' => Driver::where('vendor_profile_id', $profile->id)->where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'vehicles' => Vehicle::where('vendor_profile_id', $profile->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration_number']),
        ]);
    }

    public function eligible(Request $request, TaxiBooking $taxiBooking): JsonResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $drivers = $this->availability->eligibleDrivers($taxiBooking)->map(fn (array $row): array => [
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
        $taxiBooking = $this->scoped($request, $taxiBooking);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $this->dispatch->addNote($taxiBooking, $request->user(), $validated['body']);

        return back()->with('flash', 'Operational note added.');
    }

    public function notes(Request $request, TaxiBooking $taxiBooking): JsonResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);
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
     * Smart dispatch recommendations for OWN bookings only (12A.7).
     */
    public function recommendations(Request $request, TaxiBooking $taxiBooking): JsonResponse
    {
        $taxiBooking = $this->scoped($request, $taxiBooking);

        return response()->json($this->recommendations->recommend($taxiBooking));
    }
}
