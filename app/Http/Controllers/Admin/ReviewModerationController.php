<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Inertia\Inertia;

class ReviewModerationController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Reviews', [
            'reviews' => Review::with('user:id,name', 'package:id,title')->latest()->paginate(15),
        ]);
    }

    public function approve(Review $review)
    {
        $review->update(['is_approved' => true]);

        return back()->with('flash', 'Review approved.');
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return back()->with('flash', 'Review removed.');
    }
}
