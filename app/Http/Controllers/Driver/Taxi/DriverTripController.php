<?php

namespace App\Http\Controllers\Driver\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\TaxiBooking;
use App\Services\TaxiAvailabilityService;
use App\Services\TaxiBookingService;
use App\Services\TaxiDispatchService;
use App\Services\TaxiDriverLocationService;
use App\Services\TaxiRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver Portal trips (Phase 12A.4).
 *
 * A driver only ever sees bookings with an OPEN assignment row for their
 * own Driver record — enforced per endpoint via assignedTrip(), which
 * 404s otherwise. Status changes reuse the server-side state machine;
 * the driver allow-list below is a subset of it (no cancellations —
 * those stay with dispatch).
 */
class DriverTripController extends DriverPortalController
{
    /**
     * Driver-operable ride transitions. Cancelled is intentionally
     * absent: ending a booking is dispatch-owned; drivers report
     * no-show instead.
     *
     * @return array<int, string>
     */
    public static function allowedStatuses(): array
    {
        return [
            TaxiBookingStatus::EnRoute->value,
            TaxiBookingStatus::Arrived->value,
            TaxiBookingStatus::PassengerOnBoard->value,
            TaxiBookingStatus::Completed->value,
            TaxiBookingStatus::NoShow->value,
        ];
    }

    public function __construct(
        protected TaxiBookingService $bookings,
        protected TaxiDispatchService $dispatch,
        protected TaxiDriverLocationService $tracking,
        protected TaxiRouteService $routes,
    ) {}

    public function index(Request $request): Response
    {
        $driver = $this->driver($request);
        $view = (string) $request->query('view', 'upcoming');

        $base = TaxiBooking::with([
            'assignedVehicle:id,name,registration_number',
            'vehicleType:id,name',
        ]);

        $trips = match ($view) {
            'active' => (clone $base)->whereHas('assignments', fn ($q) => $q->open()->where('driver_id', $driver->id))
                ->whereIn('status', [
                    TaxiBookingStatus::EnRoute->value,
                    TaxiBookingStatus::Arrived->value,
                    TaxiBookingStatus::PassengerOnBoard->value,
                ])->orderBy('pickup_at')->paginate(10)->withQueryString(),
            'history' => (clone $base)->whereHas('assignments', fn ($q) => $q->where('driver_id', $driver->id))
                ->whereIn('status', array_merge(
                    TaxiDispatchService::buckets()['completed'],
                    TaxiDispatchService::buckets()['cancelled'],
                    TaxiDispatchService::buckets()['no_show'],
                ))->latest('pickup_at')->paginate(10)->withQueryString(),
            default => (clone $base)->whereHas('assignments', fn ($q) => $q->open()->where('driver_id', $driver->id))
                ->where('status', TaxiBookingStatus::DriverAssigned->value)
                ->orderBy('pickup_at')->paginate(10)->withQueryString(),
        };

        if (! in_array($view, ['upcoming', 'active', 'history'], true)) {
            $view = 'upcoming';
        }

        $trips->getCollection()->transform(function (TaxiBooking $booking) use ($driver): TaxiBooking {
            $booking->setAttribute('allowed_transitions', collect($booking->status()->allowedTransitions())
                ->filter(fn (TaxiBookingStatus $s): bool => in_array($s->value, self::allowedStatuses(), true))
                ->map(fn (TaxiBookingStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values());
            $booking->setAttribute('acknowledged', (bool) $booking->assignments()->open()->where('driver_id', $driver->id)->whereNotNull('acknowledged_at')->exists());

            return $this->hideFinancials($booking);
        });

        return Inertia::render('Driver/Taxi/Trips/Index', [
            'trips' => $trips,
            'view' => $view,
            'activeTrip' => TaxiBooking::with(['assignedVehicle:id,name,registration_number'])
                ->whereHas('assignments', fn ($q) => $q->open()->where('driver_id', $driver->id))
                ->whereIn('status', TaxiAvailabilityService::activeTripStatuses())
                ->where('status', '!=', TaxiBookingStatus::DriverAssigned->value)
                ->orderBy('pickup_at')->first()?->makeHidden(DriverPortalController::hiddenFinancialAttributes()),
        ]);
    }

    public function show(Request $request, TaxiBooking $taxiBooking): Response
    {
        $driver = $this->driver($request);
        $taxiBooking = $this->assignedTrip($request, $taxiBooking);

        $taxiBooking->load([
            'vendorProfile:id,business_name',
            'vehicleType:id,name,passenger_capacity',
            'assignedVehicle:id,name,registration_number,make,model',
            'statusHistories.changer:id,name',
        ]);

        $assignment = $taxiBooking->assignments()->open()->where('driver_id', $driver->id)->latest('assigned_at')->firstOrFail();

        $latest = $this->tracking->latestFor($driver);

        return Inertia::render('Driver/Taxi/Trips/Show', [
            'booking' => $this->hideFinancials($taxiBooking),
            'assignment' => $assignment,
            'vehicle' => $taxiBooking->assignedVehicle,
            'notes' => $taxiBooking->notes()->where('visible_to_driver', true)->latest()->limit(20)->get(),
            'tracking' => [
                'freshness' => $this->tracking->freshness($latest),
                'last_captured_at' => $latest?->captured_at?->toISOString(),
            ],
            'tripMap' => $this->routes->tripMapPayload($taxiBooking, $latest),
            'allowedTransitions' => collect($taxiBooking->status()->allowedTransitions())
                ->filter(fn (TaxiBookingStatus $s): bool => in_array($s->value, self::allowedStatuses(), true))
                ->map(fn (TaxiBookingStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
        ]);
    }

    public function status(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->assignedTrip($request, $taxiBooking);

        $validated = $request->validate([
            'status' => ['required', Rule::in(self::allowedStatuses())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // The actor is the driver themselves: vendor/customer are notified
        // by the service, but no self-notification is sent.
        $this->bookings->changeStatus(
            $taxiBooking,
            TaxiBookingStatus::from($validated['status']),
            $request->user(),
            $validated['note'] ?? null,
            notifyDriver: false,
        );

        return back()->with('flash', 'Trip status updated.');
    }

    public function acknowledge(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $driver = $this->driver($request);
        $taxiBooking = $this->assignedTrip($request, $taxiBooking);

        $assignment = $taxiBooking->assignments()->open()->where('driver_id', $driver->id)->latest('assigned_at')->firstOrFail();

        $this->bookings->acknowledgeAssignment($assignment, $request->user());

        return back()->with('flash', 'Trip acknowledged.');
    }

    public function storeNote(Request $request, TaxiBooking $taxiBooking): RedirectResponse
    {
        $taxiBooking = $this->assignedTrip($request, $taxiBooking);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $taxiBooking->notes()->create([
            'author_id' => $request->user()->id,
            'body' => mb_substr(trim($validated['body']), 0, 2000),
            'visible_to_driver' => true,
        ]);

        return back()->with('flash', 'Operational note added.');
    }

    public function notes(Request $request, TaxiBooking $taxiBooking): JsonResponse
    {
        $taxiBooking = $this->assignedTrip($request, $taxiBooking);

        return response()->json([
            'notes' => $taxiBooking->notes()->where('visible_to_driver', true)->latest()->limit(50)->get(),
        ]);
    }

    /**
     * Trip map payload for the driver's OWN trip (markers + ETA/route).
     */
    public function routeMap(Request $request, TaxiBooking $taxiBooking): JsonResponse
    {
        $driver = $this->driver($request);
        $taxiBooking = $this->assignedTrip($request, $taxiBooking);

        return response()->json($this->routes->tripMapPayload(
            $taxiBooking,
            $this->tracking->latestFor($driver),
        ));
    }
}
