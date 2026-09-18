<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTourAddonRequest;
use App\Models\TourAddon;
use App\Models\TourPackage;
use App\Services\VendorEntitlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TourAddonController extends Controller
{
    use AuthorizesRequests;

    public function index(TourPackage $tour): Response
    {
        $this->authorize('update', $tour);

        return Inertia::render('Vendor/Tours/Addons', [
            'tour' => $tour->only(['id', 'title']),
            'addons' => $tour->addons()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(StoreTourAddonRequest $request, TourPackage $tour): RedirectResponse
    {
        $this->authorize('update', $tour);

        // Phase 11: per-tour add-on caps are server-enforced.
        app(VendorEntitlementService::class)->assertCanCreateAddon($tour);

        $tour->addons()->create($this->addonData($request));

        return back()->with('flash', 'Add-on added.');
    }

    public function update(StoreTourAddonRequest $request, TourPackage $tour, TourAddon $addon): RedirectResponse
    {
        abort_unless((int) $addon->tour_package_id === (int) $tour->id, 404);
        $this->authorize('update', $tour);
        $this->authorize('update', $addon);

        $addon->update($this->addonData($request));

        return back()->with('flash', 'Add-on updated. Past bookings are unchanged.');
    }

    public function toggle(TourPackage $tour, TourAddon $addon): RedirectResponse
    {
        abort_unless((int) $addon->tour_package_id === (int) $tour->id, 404);
        $this->authorize('update', $tour);

        $addon->update(['is_active' => ! $addon->is_active]);

        return back()->with('flash', $addon->is_active ? 'Add-on activated.' : 'Add-on deactivated.');
    }

    public function destroy(TourPackage $tour, TourAddon $addon): RedirectResponse
    {
        abort_unless((int) $addon->tour_package_id === (int) $tour->id, 404);
        $this->authorize('update', $tour);

        $addon->delete();

        return back()->with('flash', 'Add-on removed. Past bookings keep their snapshot.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function addonData(StoreTourAddonRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'pricing_type' => $validated['pricing_type'],
            'price' => $validated['price'],
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'max_quantity' => $validated['max_quantity'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }
}
