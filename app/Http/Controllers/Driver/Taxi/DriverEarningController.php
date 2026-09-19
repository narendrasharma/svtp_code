<?php

namespace App\Http\Controllers\Driver\Taxi;

use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverPayout;
use App\Services\TaxiDriverEarningService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver earnings portal (Phase 12A.10).
 *
 * Identity-based access only: every query is pinned to the driver's own
 * record resolved from the authenticated user. Read-only — drivers can
 * never edit earnings, create payouts or alter rates.
 */
class DriverEarningController extends DriverPortalController
{
    public function __construct(protected TaxiDriverEarningService $earnings) {}

    public function index(Request $request): Response
    {
        $driver = $this->driver($request);

        $earnings = TaxiDriverEarning::with(['booking:id,reference,pickup_at,status'])
            ->where('driver_id', $driver->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('earned_at')
            ->paginate(15)
            ->withQueryString();

        $payouts = TaxiDriverPayout::where('driver_id', $driver->id)
            ->latest('id')
            ->paginate(10, ['*'], 'payouts_page')
            ->withQueryString();

        return Inertia::render('Driver/Taxi/Earnings/Index', [
            'earnings' => $earnings,
            'payouts' => $payouts,
            'summary' => $this->earnings->summaryForDriver($driver),
            'summaries' => TaxiDriverEarning::where('driver_id', $driver->id)->distinct()->pluck('currency')
                ->map(fn (string $currency) => $this->earnings->summaryForDriver($driver, $currency))->values(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(Request $request, TaxiDriverEarning $earning): Response
    {
        $driver = $this->driver($request);
        abort_unless((int) $earning->driver_id === (int) $driver->id, 404);

        $earning->load([
            'booking:id,reference,pickup_at,drop_address,status',
            'adjustments',
            'payoutItems.payout:id,payout_number,status,paid_at,payment_reference',
        ]);

        return Inertia::render('Driver/Taxi/Earnings/Show', [
            'earning' => $earning,
            'unpaidRemainder' => $earning->unpaidRemainder(),
        ]);
    }
}
