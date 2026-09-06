<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Tags/Index', [
            'tags' => Tag::withCount('tourPackages')->latest()->paginate(10),
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
