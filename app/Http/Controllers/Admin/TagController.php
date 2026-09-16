<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Tag::withCount('tourPackages');

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
        // Status filter (active / inactive) – tags have is_active column
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
        $sortable = ['name', 'created_at', 'is_active']; // added is_active
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, $sortable)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        // -----------------------------------------------------------------
        // Per‑page selector
        // -----------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $tags = $query->paginate($perPage)->appends($request->query());

        return Inertia::render('Admin/Tags/Index', [
            'tags' => $tags,
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
        return Inertia::render('Admin/Tags/Form');
    }

    public function store(SaveTagRequest $request): RedirectResponse
    {
        Tag::create($request->validated());

        return redirect()->route('admin.tags.index')->with('flash', 'Tag created.');
    }

    public function edit(Tag $tag): Response
    {
        return Inertia::render('Admin/Tags/Form', ['tag' => $tag]);
    }

    public function update(SaveTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return redirect()->route('admin.tags.index')->with('flash', 'Tag updated.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return back()->with('flash', 'Tag removed.');
    }
}
