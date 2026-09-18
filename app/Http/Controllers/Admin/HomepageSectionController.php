<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderHomepageSectionsRequest;
use App\Http\Requests\UpdateHomepageSectionRequest;
use App\Models\HomepageSection;
use App\Services\HomepageSectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomepageSectionController extends Controller
{
    public function __construct(protected HomepageSectionService $sections) {}

    public function index(): Response
    {
        $this->sections->ensureStored();

        return Inertia::render('Admin/HomepageSections/Index', [
            'sections' => $this->sections->all(),
        ]);
    }

    public function update(UpdateHomepageSectionRequest $request, HomepageSection $homepageSection): RedirectResponse
    {
        // Partial updates: only persist fields the request actually sent so a
        // visibility toggle never wipes section settings and vice versa.
        $attributes = [];
        if ($request->has('is_active')) {
            $attributes['is_active'] = $request->boolean('is_active');
        }
        if ($request->has('settings')) {
            $settings = $request->validated('settings') ?? [];
            $attributes['settings'] = $settings === [] ? null : $settings;
        }

        DB::transaction(function () use ($homepageSection, $attributes): void {
            HomepageSection::whereKey($homepageSection->id)->lockForUpdate()->firstOrFail();
            $homepageSection->update($attributes);
        });

        return back()->with('flash', 'Homepage section saved.');
    }

    public function reorder(ReorderHomepageSectionsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            // Unknown legacy keys stay untouched and never render; the manager
            // only ever submits the known system-defined set.
            $knownIds = HomepageSection::query()
                ->whereIn('section_key', HomepageSectionService::defaultOrder())
                ->pluck('id')
                ->all();
            $request->validateCompleteSet($knownIds);
            foreach ($request->validated('sections') as $section) {
                HomepageSection::whereKey($section['id'])->update(['sort_order' => $section['sort_order']]);
            }
        });

        return back()->with('flash', 'Section order saved.');
    }
}
