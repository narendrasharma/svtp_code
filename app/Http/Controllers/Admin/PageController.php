<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(Request $request): Response
    {
        // Base query
        $query = Page::query();

        // -----------------------------------------------------------------
        // 1. Search (title, slug, meta_title)
        // -----------------------------------------------------------------
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('meta_title', 'like', "%{$search}%");
            });
        }

        // -----------------------------------------------------------------
        // 2. Status filter
        // -----------------------------------------------------------------
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // -----------------------------------------------------------------
        // 3. Template filter
        // -----------------------------------------------------------------
        $availableTemplates = Page::availableTemplates();
        $template = $request->input('template');
        if ($template && in_array($template, $availableTemplates, true)) {
            $query->where('template', $template);
        }

        // -----------------------------------------------------------------
        // 4. Sorting
        // -----------------------------------------------------------------
        $allowedSorts = ['title', 'template', 'sort_order', 'is_active', 'created_at'];
        $sort = $request->input('sort', 'sort_order');
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sort_order';
        }

        $direction = strtolower($request->input('direction', 'asc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query->orderBy($sort, $direction);

        // -----------------------------------------------------------------
        // 5. Per‑page control
        // -----------------------------------------------------------------
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        // -----------------------------------------------------------------
        // 6. Pagination (preserve query string)
        // -----------------------------------------------------------------
        $pages = $query->paginate($perPage)->appends($request->query());

        // -----------------------------------------------------------------
        // 7. Return Inertia response
        // -----------------------------------------------------------------
        return Inertia::render('Admin/Pages/Index', [
            'pages' => $pages,
            'templates' => $availableTemplates,
            'filters' => $request->only([
                'search',
                'status',
                'template',
                'sort',
                'direction',
                'per_page',
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Form', [
            'templates' => Page::availableTemplates(),
        ]);
    }

    public function store(SavePageRequest $request): RedirectResponse
    {
        Page::create($request->validated());

        return redirect()
            ->route('admin.pages.index')
            ->with('flash', 'Page created.');
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Form', [
            'page' => $page,
            'templates' => Page::availableTemplates(),
        ]);
    }

    public function update(SavePageRequest $request, Page $page): RedirectResponse
    {
        $page->update($request->validated());

        return redirect()
            ->route('admin.pages.index')
            ->with('flash', 'Page updated.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return back()->with('flash', 'Page removed.');
    }
}
