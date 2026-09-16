<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTourCategoryRequest;
use App\Models\TourCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TourCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TourCategory::withCount('tourPackages');

        // -----------------------------------------------------------------
        // Search (name, slug)
        // -----------------------------------------------------------------
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // -----------------------------------------------------------------
        // Status filter (active / inactive)
        // -----------------------------------------------------------------
        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // -----------------------------------------------------------------
        // Sorting
        // -----------------------------------------------------------------
        $sortable = ['name', 'sort_order', 'is_active', 'created_at'];
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, $sortable)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('sort_order')->orderBy('name');
        }

        // -----------------------------------------------------------------
        // Per‑page selector
        // -----------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 15;

        $categories = $query->paginate($perPage)->appends($request->query());

        return Inertia::render('Admin/TourCategories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'per_page' => $perPage,
                'sort' => $sort ?? '',
                'direction' => $direction ?? '',
            ],
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
