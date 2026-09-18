<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlackoutDateRequest;
use App\Http\Requests\UpdateTourAvailabilityRequest;
use App\Models\TourBlackoutDate;
use App\Models\TourPackage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TourAvailabilityController extends Controller
{
    use AuthorizesRequests;

    public function show(TourPackage $tour): Response
    {
        $this->authorize('update', $tour);

        return Inertia::render('Vendor/Tours/Availability', [
            'tour' => $tour->only(['id', 'title', 'booking_enabled', 'available_weekdays', 'min_advance_days', 'max_advance_days']),
            'blackouts' => $tour->blackoutDates()->orderBy('date')->get(['id', 'date', 'reason']),
        ]);
    }

    public function update(UpdateTourAvailabilityRequest $request, TourPackage $tour): RedirectResponse
    {
        $this->authorize('update', $tour);

        $tour->update($request->validated());

        return back()->with('flash', 'Availability settings saved.');
    }

    public function store(StoreBlackoutDateRequest $request, TourPackage $tour): RedirectResponse
    {
        $this->authorize('update', $tour);

        $tour->blackoutDates()->updateOrCreate(
            ['date' => $request->date('date')->toDateString()],
            ['reason' => $request->input('reason'), 'created_by' => $request->user()->id]
        );

        return back()->with('flash', 'Date blocked.');
    }

    public function destroy(TourPackage $tour, TourBlackoutDate $blackout): RedirectResponse
    {
        $this->authorize('update', $tour);
        abort_unless((int) $blackout->tour_package_id === (int) $tour->id, 404);

        $blackout->delete();

        return back()->with('flash', 'Blocked date removed.');
    }
}
