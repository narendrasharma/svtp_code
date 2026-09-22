<?php

namespace App\Http\Controllers\Vendor\Hotel;

use App\Http\Controllers\Controller;
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
        $profile = $this->profile($request);
        $filters = $request->validate(HotelReviewService::filterRules());
        unset($filters['vendor_profile_id']);

        if (! empty($filters['property_id'])) {
            Property::withTrashed()->where('vendor_profile_id', $profile->id)->findOrFail($filters['property_id']);
        }

        return Inertia::render('Vendor/Hotel/Reviews/Index', [
            'reviews' => $this->reviews->managementQuery($filters, $profile->id)->paginate(20)->withQueryString()
                ->through(fn (HotelReview $review): array => $review->managementPayload()),
            'filters' => $filters,
            'properties' => Property::withTrashed()->where('vendor_profile_id', $profile->id)->orderBy('name')->limit(100)->get(['id', 'name']),
            'statuses' => HotelReview::STATUSES,
        ]);
    }

    public function show(Request $request, HotelReview $review): Response
    {
        $review = $this->scoped($request, $review);
        $review->load(['user:id,name', 'property:id,name,slug,vendor_profile_id']);

        return Inertia::render('Vendor/Hotel/Reviews/Show', [
            'review' => $review->managementPayload(),
            'categories' => HotelReview::CATEGORY_RATINGS,
            'canReply' => $this->reviews->enabled() && HotelSettings::enabled('hotel.reviews.vendor_replies_enabled') && $review->status === HotelReview::STATUS_APPROVED,
        ]);
    }

    public function reply(Request $request, HotelReview $review): RedirectResponse
    {
        $this->reviews->reply($this->scoped($request, $review), $request->user(), $request->only('reply'));

        return back()->with('flash', 'Your official property response has been saved.');
    }

    private function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        return $profile;
    }

    private function scoped(Request $request, HotelReview $review): HotelReview
    {
        $profile = $this->profile($request);
        abort_unless($review->property && (int) $review->property->vendor_profile_id === (int) $profile->id, 404);

        return $review;
    }
}
