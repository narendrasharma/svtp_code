<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderHomepageSectionsRequest;
use App\Http\Requests\UpdateHomepageSectionRequest;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Services\ActivityLogger;
use App\Services\HomepageSectionService;
use App\Services\HomepageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'merchandisingSections' => $this->sections->merchandisingAll(),
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
            if (HomepageSectionService::isSupportedType($homepageSection->section_type)) {
                $allowed = HomepageSectionService::merchandisingSettingKeys((string) $homepageSection->section_type);
                $attributes['settings'] = array_merge(
                    HomepageSectionService::mergeMerchandisingSettings((string) $homepageSection->section_type, $homepageSection->settings),
                    array_intersect_key($settings, array_flip($allowed))
                );
            } else {
                $attributes['settings'] = $settings === [] ? null : $settings;
            }
        }
        if ($request->has('source_mode')) {
            $attributes['source_mode'] = $request->validated('source_mode');
        }
        if ($request->has('item_limit')) {
            $attributes['item_limit'] = $request->integer('item_limit');
        }
        $manualItems = $request->has('items') ? $request->validated('items', []) : null;

        DB::transaction(function () use ($homepageSection, $attributes, $manualItems): void {
            $homepageSection = HomepageSection::whereKey($homepageSection->id)->lockForUpdate()->firstOrFail();
            $homepageSection->update($attributes);

            if ($manualItems !== null && HomepageSectionService::isSupportedType($homepageSection->section_type)) {
                HomepageSectionItem::query()->where('homepage_section_id', $homepageSection->id)->delete();

                foreach ($manualItems as $item) {
                    HomepageSectionItem::query()->create([
                        'homepage_section_id' => $homepageSection->id,
                        'entity_type' => $item['entity_type'],
                        'entity_id' => $item['entity_id'],
                        'sort_order' => $item['sort_order'],
                    ]);
                }
            }
        });

        app(ActivityLogger::class)->log('homepage.section.updated', 'content', 'Homepage merchandising section updated.', $homepageSection, null, $attributes);

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

    public function reorderMerchandising(ReorderHomepageSectionsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $knownIds = HomepageSection::query()
                ->merchandising()
                ->whereIn('section_type', HomepageSectionService::merchandisingOrder())
                ->pluck('id')
                ->all();
            $request->validateCompleteSet($knownIds);

            foreach ($request->validated('sections') as $section) {
                HomepageSection::query()->whereKey($section['id'])->whereNotNull('section_type')->update(['sort_order' => $section['sort_order']]);
            }
        });

        app(ActivityLogger::class)->log('homepage.sections.reordered', 'content', 'Homepage merchandising sections reordered.');

        return back()->with('flash', 'Section order saved.');
    }

    public function searchItems(Request $request, HomepageSection $homepageSection, HomepageService $homepage): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'entity_type' => ['required', 'string', 'max:40'],
        ]);

        return response()->json([
            'items' => $homepage->searchManualItems($homepageSection, $validated['q'], $validated['entity_type']),
        ]);
    }
}
