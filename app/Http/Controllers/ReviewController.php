<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicReviewRequest;
use App\Models\Booking;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
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

    public function storePublic(StorePublicReviewRequest $request, TourPackage $package): RedirectResponse
    {
        $package->reviews()->create([
            'reviewer_name' => $request->validated('name'),
            'reviewer_email' => $request->validated('email'),
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
            'is_approved' => false,
        ]);

        return back()->with('flash', 'Thank you. Your review was submitted and is awaiting moderation.');
    }
}
