<?php

namespace App\Http\Controllers\Admin;

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

    public function show(TourPackage $package): Response
    {
        return Inertia::render('Admin/Packages/Availability', [
            'tour' => $package->only(['id', 'title', 'slug', 'booking_enabled', 'available_weekdays', 'min_advance_days', 'max_advance_days']),
            'blackouts' => $package->blackoutDates()->orderBy('date')->get(['id', 'date', 'reason']),
        ]);
    }

    public function update(UpdateTourAvailabilityRequest $request, TourPackage $package): RedirectResponse
    {
        $package->update($request->validated());

        return back()->with('flash', 'Availability settings saved.');
    }

    public function store(StoreBlackoutDateRequest $request, TourPackage $package): RedirectResponse
    {
        $package->blackoutDates()->updateOrCreate(
            ['date' => $request->date('date')->toDateString()],
            ['reason' => $request->input('reason'), 'created_by' => $request->user()->id]
        );

        return back()->with('flash', 'Date blocked.');
    }

    public function destroy(TourPackage $package, TourBlackoutDate $blackout): RedirectResponse
    {
        abort_unless((int) $blackout->tour_package_id === (int) $package->id, 404);

        $blackout->delete();

        return back()->with('flash', 'Blocked date removed.');
    }
}
