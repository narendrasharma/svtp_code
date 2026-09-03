<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTourCategoryRequest;
use App\Models\TourCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TourCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/TourCategories/Index', [
            'categories' => TourCategory::withCount('tourPackages')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TourCategories/Form');
    }

    public function store(SaveTourCategoryRequest $request): RedirectResponse
    {
        TourCategory::create($request->validated());

        return redirect()->route('admin.tour-categories.index')->with('flash', 'Tour category created.');
    }

    public function edit(TourCategory $tourCategory): Response
    {
        return Inertia::render('Admin/TourCategories/Form', ['category' => $tourCategory]);
    }

    public function update(SaveTourCategoryRequest $request, TourCategory $tourCategory): RedirectResponse
    {
        $tourCategory->update($request->validated());

        return redirect()->route('admin.tour-categories.index')->with('flash', 'Tour category updated.');
    }

    public function destroy(TourCategory $tourCategory): RedirectResponse
    {
        $tourCategory->delete();

        return back()->with('flash', 'Tour category removed. Packages have been left uncategorized.');
    }
}
