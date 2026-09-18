<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTourAddonRequest;
use App\Models\TourAddon;
use App\Models\TourPackage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TourAddonController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, TourPackage $package): Response
    {
        $this->authorize('viewAny', TourAddon::class);

        return Inertia::render('Admin/Packages/Addons', [
            'tour' => $package->only(['id', 'title', 'slug']),
            'addons' => $package->addons()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(StoreTourAddonRequest $request, TourPackage $package): RedirectResponse
    {
        $this->authorize('create', [TourAddon::class, $package]);

        $package->addons()->create($this->addonData($request));

        return back()->with('flash', 'Add-on added.');
    }

    public function update(StoreTourAddonRequest $request, TourPackage $package, TourAddon $addon): RedirectResponse
    {
        abort_unless((int) $addon->tour_package_id === (int) $package->id, 404);
        $this->authorize('update', $addon);

        $addon->update($this->addonData($request));

        return back()->with('flash', 'Add-on updated. Past bookings are unchanged.');
    }

    public function toggle(TourPackage $package, TourAddon $addon): RedirectResponse
    {
        abort_unless((int) $addon->tour_package_id === (int) $package->id, 404);
        $this->authorize('update', $addon);

        $addon->update(['is_active' => ! $addon->is_active]);

        return back()->with('flash', $addon->is_active ? 'Add-on activated.' : 'Add-on deactivated.');
    }

    public function destroy(TourPackage $package, TourAddon $addon): RedirectResponse
    {
        abort_unless((int) $addon->tour_package_id === (int) $package->id, 404);
        $this->authorize('delete', $addon);

        // Snapshots keep history intact (booking_addons.tour_addon_id
        // nulls on delete) — past bookings never change.
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
