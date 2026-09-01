<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        $this->authorize('view', $booking);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->review()->updateOrCreate([], $data + [
            'user_id' => $booking->user_id,
            'package_id' => $booking->package_id,
            'is_approved' => false, // moderated by admin before it appears publicly
        ]);

        return back()->with('flash', 'Thanks for your feedback — it will appear once reviewed.');
    }
}
