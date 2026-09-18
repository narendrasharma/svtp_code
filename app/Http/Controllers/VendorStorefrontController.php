<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\VendorProfile;
use App\Services\VendorEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public vendor storefront (Phase 11).
 *
 * Only approved + active vendors with the storefront enabled are visible;
 * everything else 404s. The payload is presentation-only — KYC docs,
 * legal contact details, payout data and admin notes are never exposed.
 */
class VendorStorefrontController extends Controller
{
    public function show(Request $request, VendorProfile $vendor): Response
    {
        abort_unless($vendor->isPubliclyVisible(), 404);

        $entitlements = app(VendorEntitlementService::class);

        if (! $entitlements->canUseStorefront($vendor)) {
            abort(404);
        }

        $vendor->loadCount([
            'tours as public_tours_count' => fn ($query) => $query->publiclyVisible(),
        ]);

        $tours = $vendor->tours()
            ->publiclyVisible()
            ->with(['city:id,name', 'category:id,name,slug'])
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->orderByDesc('is_featured')
            ->latest()
            ->paginate(9)
            ->withQueryString();

        $rating = DB::table('reviews')
            ->join('tour_packages', 'tour_packages.id', '=', 'reviews.package_id')
            ->where('tour_packages.vendor_profile_id', $vendor->id)
            ->where('reviews.is_approved', true)
            ->selectRaw('AVG(reviews.rating) as avg_rating, COUNT(*) as reviews_count')
            ->first();

        $reviews = Review::where('is_approved', true)
            ->whereIn('package_id', $vendor->tours()->publiclyVisible()->select('id'))
            ->with('package:id,title,slug')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($review): array => [
                'id' => $review->id,
                'reviewer_name' => $review->reviewer_name ?: $review->user?->name ?: 'Guest',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
                'is_verified_booking' => $review->booking_id !== null,
                'tour' => $review->package ? ['title' => $review->package->title, 'slug' => $review->package->slug] : null,
            ])->all();

        return Inertia::render('Vendors/Show', [
            'vendor' => [
                'business_name' => $vendor->business_name,
                'slug' => $vendor->slug,
                'logo' => $vendor->logo_path ? Storage::disk('public')->url($vendor->logo_path) : null,
                'cover' => $vendor->cover_path ? Storage::disk('public')->url($vendor->cover_path) : null,
                'description' => $vendor->publicDescription(),
                'city' => $vendor->city,
                'state' => $vendor->state,
                'country_code' => $vendor->country_code,
                'website' => $vendor->website,
                'public_phone' => $vendor->public_phone,
                'public_email' => $vendor->public_email,
                'social_links' => $vendor->social_links ?? [],
                'is_verified' => $vendor->isKycVerified(),
                'joined_at' => $vendor->approved_at?->toDateString(),
                'tours_count' => $vendor->public_tours_count,
                'average_rating' => $rating->avg_rating !== null ? round((float) $rating->avg_rating, 2) : null,
                'reviews_count' => (int) ($rating->reviews_count ?? 0),
            ],
            'tours' => $tours,
            'reviews' => $reviews,
            'seo' => [
                'title' => $vendor->business_name.' Tours & Packages',
                'description' => Str::limit((string) ($vendor->publicDescription() ?? ''), 160),
                'canonical' => route('vendors.show', $vendor),
                'image' => $vendor->logo_path ? Storage::disk('public')->url($vendor->logo_path) : null,
            ],
        ]);
    }
}
