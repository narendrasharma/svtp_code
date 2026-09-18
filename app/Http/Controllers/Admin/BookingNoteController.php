<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Operational booking notes. Internal notes stay in the admin area;
 * customer/vendor shapes must exclude them.
 */
class BookingNoteController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $booking->notes()->create([
            'author_id' => $request->user()->id,
            'body' => trim($validated['body']),
            'is_internal' => $validated['is_internal'] ?? true,
        ]);

        $this->bookings->logNote($booking, $request->user(), 'Note added.', true);

        return back()->with('flash', 'Note added.');
    }
}
