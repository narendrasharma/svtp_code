<?php

namespace App\Http\Controllers\Driver\Taxi;

use App\Enums\TaxiDispatchOfferStatus;
use App\Models\TaxiDispatchOffer;
use App\Services\TaxiAutoDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver Portal dispatch offers (Phase 12A.8).
 *
 * A driver only ever sees and answers their OWN offers, resolved from
 * the authenticated Driver identity. Offer ids from the request are
 * scoped to that driver and 404 otherwise; expiry and assignment
 * races are revalidated inside the service transaction.
 */
class DriverOfferController extends DriverPortalController
{
    public function __construct(protected TaxiAutoDispatchService $autoDispatch) {}

    public function index(Request $request): Response
    {
        $driver = $this->driver($request);

        $pending = TaxiDispatchOffer::with([
            'booking:id,reference,status,pickup_at,pickup_address,drop_address,trip_type',
            'vehicle:id,name,registration_number',
        ])
            ->pending()
            ->where('driver_id', $driver->id)
            ->latest('offered_at')
            ->get();

        $history = TaxiDispatchOffer::with([
            'booking:id,reference,status',
            'vehicle:id,name,registration_number',
        ])
            ->where('driver_id', $driver->id)
            ->where('status', '!=', TaxiDispatchOfferStatus::Pending->value)
            ->latest('responded_at')
            ->limit(10)
            ->get();

        return Inertia::render('Driver/Taxi/Offers/Index', [
            'pending' => $pending,
            'history' => $history,
        ]);
    }

    protected function ownOffer(Request $request, TaxiDispatchOffer $offer): TaxiDispatchOffer
    {
        $driver = $this->driver($request);

        abort_unless((int) $offer->driver_id === (int) $driver->id, 404);

        return $offer;
    }

    public function accept(Request $request, TaxiDispatchOffer $offer): RedirectResponse
    {
        $driver = $this->driver($request);
        $this->ownOffer($request, $offer);

        $this->autoDispatch->acceptOffer($offer, $driver);

        return back()->with('flash', 'Offer accepted — the trip is assigned to you.');
    }

    public function reject(Request $request, TaxiDispatchOffer $offer): RedirectResponse
    {
        $driver = $this->driver($request);
        $this->ownOffer($request, $offer);

        $validated = $request->validate([
            'reason' => ['nullable', Rule::in(TaxiAutoDispatchService::REJECT_REASONS)],
        ]);

        $this->autoDispatch->rejectOffer($offer, $driver, $validated['reason'] ?? null);

        return back()->with('flash', 'Offer declined.');
    }

    public function pendingCount(Request $request): JsonResponse
    {
        $driver = $this->driver($request);

        return response()->json([
            'pending' => TaxiDispatchOffer::pending()->where('driver_id', $driver->id)->count(),
        ]);
    }
}
