<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\TaxiDriverEarningStatus;
use App\Http\Controllers\Controller;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverEarningAdjustment;
use App\Models\VendorProfile;
use App\Services\TaxiDriverEarningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin driver earnings (Phase 12A.10).
 *
 * Read/filter any earning, mark pending rows payable, void unpaid rows
 * and post signed adjustments. Amounts are never accepted from the
 * browser — the service recomputes everything from stored rows.
 */
class TaxiDriverEarningController extends Controller
{
    public function __construct(protected TaxiDriverEarningService $earnings) {}

    public function index(Request $request): Response
    {
        $earnings = TaxiDriverEarning::with([
            'driver:id,first_name,last_name', 'vendorProfile:id,business_name', 'booking:id,reference',
        ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('earning_number', 'like', $term)
                    ->orWhereHas('booking', fn ($b) => $b->where('reference', 'like', $term))
                    ->orWhereHas('driver', fn ($d) => $d->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', strtoupper($request->string('currency')->toString())))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('earned_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('earned_at', '<=', $request->date('date_to')))
            ->latest('earned_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Taxi/Earnings/Index', [
            'earnings' => $earnings,
            'filters' => $request->only(['search', 'status', 'vendor_id', 'driver_id', 'currency', 'date_from', 'date_to']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'statuses' => collect(TaxiDriverEarningStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(TaxiDriverEarning $earning): Response
    {
        $earning->load([
            'driver:id,first_name,last_name,phone,vendor_profile_id',
            'vendorProfile:id,business_name',
            'booking:id,reference,status,total_amount,currency,pickup_at',
            'assignment:id,assigned_at,unassigned_at',
            'adjustments.creator:id,name',
            'payoutItems.payout:id,payout_number,status,paid_at',
        ]);

        return Inertia::render('Admin/Taxi/Earnings/Show', [
            'earning' => $earning,
            'unpaidRemainder' => $earning->unpaidRemainder(),
        ]);
    }

    public function markPayable(Request $request, TaxiDriverEarning $earning): RedirectResponse
    {
        $this->earnings->markPayable($earning, $request->user());

        return back()->with('flash', "Earning {$earning->earning_number} is now payable.");
    }

    public function void(Request $request, TaxiDriverEarning $earning): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->earnings->voidEarning($earning, $request->user(), $validated['reason'] ?? null);

        return back()->with('flash', "Earning {$earning->earning_number} voided.");
    }

    public function storeAdjustment(Request $request, TaxiDriverEarning $earning): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:-999999999.99', 'max:999999999.99', 'not_in:0'],
            'kind' => ['required', Rule::in(TaxiDriverEarningAdjustment::KINDS)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->earnings->applyAdjustment(
            $earning,
            (float) $validated['amount'],
            $validated['kind'],
            $validated['reason'],
            $request->user(),
        );

        return back()->with('flash', 'Adjustment posted. Net earning updated.');
    }
}
