<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelReview;
use App\Services\HotelReviewService;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelReviewController extends Controller
{
    public function __construct(private HotelReviewService $reviews) {}

    public function show(Request $request, HotelBooking $booking): Response|RedirectResponse
    {
        abort_unless($this->reviews->enabled(), 404);
        abort_unless($booking->user_id !== null && (int) $booking->user_id === (int) $request->user()->id, 404);
        $booking->load('review.user:id,name');
        $eligibility = $this->reviews->eligibility($booking, $request->user());

        if (! $eligibility['can_review'] && ! $eligibility['has_review']) {
            return redirect()->route('account.hotel-bookings.show', $booking)->with('flash', $eligibility['reason']);
        }

        return Inertia::render('Account/HotelBookings/Review', [
            'booking' => ['id' => $booking->id, 'property_name' => $booking->property_name_snapshot],
            'review' => $booking->review ? [
                ...$booking->review->publicPayload(),
                ...$booking->review->only(array_keys(HotelReview::CATEGORY_RATINGS)),
                'status' => $booking->review->status,
                'rejection_reason' => $booking->review->rejection_reason,
            ] : null,
            'eligibility' => $eligibility,
            'categories' => HotelReview::CATEGORY_RATINGS,
            'minimumCommentLength' => HotelSettings::minimumReviewLength(),
            'moderationEnabled' => HotelSettings::enabled('hotel.reviews.moderation_enabled'),
        ]);
    }

    public function store(Request $request, HotelBooking $booking): RedirectResponse
    {
        $review = $this->reviews->submit($booking, $request->user(), $request->only([
            'overall_rating', ...array_keys(HotelReview::CATEGORY_RATINGS), 'title', 'comment',
        ]));

        return redirect()->route('account.hotel-reviews.show', $booking)->with('flash',
            $review->status === HotelReview::STATUS_APPROVED ? 'Your verified stay review is published.' : 'Thank you. Your review is pending moderation.');
    }

    public function update(Request $request, HotelBooking $booking): RedirectResponse
    {
        $this->reviews->updatePending($booking, $request->user(), $request->only([
            'overall_rating', ...array_keys(HotelReview::CATEGORY_RATINGS), 'title', 'comment',
        ]));

        return back()->with('flash', 'Your review was updated and is still pending moderation.');
    }
}
