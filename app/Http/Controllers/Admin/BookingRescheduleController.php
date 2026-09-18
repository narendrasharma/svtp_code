<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingRescheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Booking date changes (Phase 11.5B). Separate from cancellation: the
 * booking keeps its identity, the old date stays in history, and money
 * only moves through explicit payment/refund actions afterwards.
 */
class BookingRescheduleController extends Controller
{
    public function __construct(protected BookingRescheduleService $reschedules) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'new_travel_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $reschedule = $this->reschedules->reschedule(
            $booking,
            $validated['new_travel_date'],
            $validated['reason'],
            $request->user()
        );

        $message = "Booking rescheduled to {$reschedule->new_travel_date->toDateString()}.";

        if ($reschedule->price_difference > 0) {
            $message .= ' Additional ₹'.number_format((float) $reschedule->price_difference, 2).' now due — record a payment to collect it.';
        } elseif ($reschedule->price_difference < 0) {
            $message .= ' Total reduced by ₹'.number_format(abs((float) $reschedule->price_difference), 2).' — no automatic refund issued.';
        }

        return back()->with('flash', $message);
    }
}
