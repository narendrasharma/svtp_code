<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\HotelReview;
use App\Models\Property;
use App\Models\VendorProfile;
use App\Services\HotelReviewService;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(private HotelReviewService $reviews) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(HotelReviewService::filterRules());

        return Inertia::render('Admin/Hotel/Reviews/Index', [
            'reviews' => $this->reviews->managementQuery($filters)->paginate(20)->withQueryString()
                ->through(fn (HotelReview $review): array => $review->managementPayload(true)),
            'filters' => $filters,
            'properties' => Property::withTrashed()->whereHas('reviews')->orderBy('name')->limit(100)->get(['id', 'name']),
            'vendors' => VendorProfile::whereIn('id', Property::withTrashed()->select('vendor_profile_id')->whereHas('reviews'))
                ->orderBy('business_name')->limit(100)->get(['id', 'business_name']),
            'statuses' => HotelReview::STATUSES,
        ]);
    }

    public function show(Request $request, HotelReview $review): Response
    {
        $review->load(['user:id,name', 'property:id,name,slug,vendor_profile_id', 'property.vendorProfile:id,business_name']);

        return Inertia::render('Admin/Hotel/Reviews/Show', [
            'review' => $review->managementPayload(true),
            'categories' => HotelReview::CATEGORY_RATINGS,
            'canModerate' => $request->user()->can('hotel.reviews.moderate'),
            'canReply' => $this->reviews->enabled() && HotelSettings::enabled('hotel.reviews.vendor_replies_enabled') && $request->user()->can('hotel.reviews.reply'),
            'activity' => ActivityLog::where('subject_type', HotelReview::class)->where('subject_id', $review->id)
                ->latest('id')->limit(30)->get(['id', 'event', 'description', 'old_values', 'new_values', 'created_at']),
        ]);
    }

    public function moderate(Request $request, HotelReview $review): RedirectResponse
    {
        $this->reviews->moderate($review, $request->user(), $request->only(['action', 'reason']));

        return back()->with('flash', 'Review moderation saved. Public ratings are up to date.');
    }

    public function reply(Request $request, HotelReview $review): RedirectResponse
    {
        $this->reviews->reply($review, $request->user(), $request->only('reply'));

        return back()->with('flash', 'Official property response published.');
    }

    public function updateReply(Request $request, HotelReview $review): RedirectResponse
    {
        $this->reviews->moderateReply($review, $request->user(), $request->only('reply'));

        return back()->with('flash', 'Property response updated.');
    }

    public function destroyReply(Request $request, HotelReview $review): RedirectResponse
    {
        $this->reviews->moderateReply($review, $request->user(), null);

        return back()->with('flash', 'Property response removed. Its audit history is retained.');
    }
}
