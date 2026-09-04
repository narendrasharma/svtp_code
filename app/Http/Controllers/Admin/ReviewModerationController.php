<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'package_id' => ['nullable', 'integer', 'exists:tour_packages,id'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'all'])],
        ]);
        $status = $filters['status'] ?? 'pending';

        $reviews = Review::query()
            ->with(['user:id,name,email', 'package:id,title,slug'])
            ->when($status !== 'all', fn ($query) => $query->where('is_approved', $status === 'approved'))
            ->when($filters['package_id'] ?? null, fn ($query, $packageId) => $query->where('package_id', $packageId))
            ->when($filters['rating'] ?? null, fn ($query, $rating) => $query->where('rating', $rating))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('reviewer_name', 'like', "%{$search}%")
                        ->orWhere('reviewer_email', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('package', fn ($packageQuery) => $packageQuery->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Reviews', [
            'reviews' => $reviews,
            'packages' => TourPackage::query()->whereHas('reviews')->orderBy('title')->get(['id', 'title']),
            'filters' => [...$filters, 'status' => $status],
            'counts' => [
                'pending' => Review::query()->where('is_approved', false)->count(),
                'approved' => Review::query()->where('is_approved', true)->count(),
            ],
        ]);
    }

    public function show(Review $review): Response
    {
        $review->load(['user:id,name,email', 'package:id,title,slug', 'booking:id,booking_reference_id']);

        return Inertia::render('Admin/Reviews/Show', ['review' => $review]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => true]);

        return back()->with('flash', 'Review approved.');
    }

    public function reject(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => false]);

        return back()->with('flash', 'Review moved to pending.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return redirect()->route('admin.reviews.index')->with('flash', 'Review deleted.');
    }
}
