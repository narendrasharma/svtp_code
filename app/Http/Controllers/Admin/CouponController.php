<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Models\Coupon;
use App\Models\TourPackage;
use App\Models\VendorProfile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Coupon::class);

        $query = Coupon::with(['vendorProfile:id,business_name'])->withCount('redemptions')->latest();

        if ($search = $request->string('search')->toString()) {
            $term = '%'.$search.'%';
            $query->where(function ($query) use ($term): void {
                $query->where('code', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        if ($request->query('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->query('status') === 'inactive') {
            $query->where('is_active', false);
        }

        if ($request->query('validity') === 'expired') {
            $query->whereNotNull('ends_at')->where('ends_at', '<', now());
        } elseif ($request->query('validity') === 'upcoming') {
            $query->whereNotNull('starts_at')->where('starts_at', '>', now());
        }

        if ($request->filled('vendor_profile_id')) {
            $query->where('vendor_profile_id', $request->integer('vendor_profile_id'));
        }

        if ($request->query('scope')) {
            $query->where('scope', $request->string('scope')->toString());
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'validity', 'vendor_profile_id', 'scope', 'per_page']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Coupon::class);

        return Inertia::render('Admin/Coupons/Form', [
            'coupon' => null,
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'tours' => TourPackage::orderBy('title')->get(['id', 'title', 'vendor_profile_id']),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $this->authorize('create', Coupon::class);

        $data = $this->couponData($request);
        $coupon = Coupon::create($data);
        $coupon->tours()->sync($data['tour_ids'] ?? []);

        return redirect()->route('admin.coupons.index')->with('flash', "Coupon {$coupon->code} created.");
    }

    public function edit(Coupon $coupon): Response
    {
        $this->authorize('update', $coupon);
        $coupon->load('tours:id');

        return Inertia::render('Admin/Coupons/Form', [
            'coupon' => [...$coupon->toArray(), 'tour_ids' => $coupon->tours->pluck('id')->all(), 'usage' => $coupon->redemptions()->count()],
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'tours' => TourPackage::orderBy('title')->get(['id', 'title', 'vendor_profile_id']),
        ]);
    }

    public function update(StoreCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);

        $data = $this->couponData($request);
        $coupon->update($data);
        $coupon->tours()->sync($data['tour_ids'] ?? []);

        return redirect()->route('admin.coupons.index')->with('flash', "Coupon {$coupon->code} updated. Past bookings are unchanged.");
    }

    public function toggle(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('flash', $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated. Past bookings are unchanged.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->authorize('delete', $coupon);

        if ($coupon->redemptions()->exists()) {
            return back()->withErrors(['coupon' => 'This coupon has redemption history. Deactivate it instead of deleting.']);
        }

        $coupon->tours()->detach();
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('flash', 'Coupon deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function couponData(StoreCouponRequest $request): array
    {
        $validated = $request->validated();

        $scope = $validated['scope'];
        $vendorId = $validated['vendor_profile_id'] ?? null;
        $tourIds = array_values(array_unique(array_map('intval', (array) ($validated['tour_ids'] ?? []))));

        if ($scope === Coupon::SCOPE_GLOBAL) {
            $vendorId = null;
            $tourIds = [];
        }

        if ($scope === Coupon::SCOPE_VENDOR) {
            $tourIds = [];

            if ($vendorId === null) {
                abort(422, 'Vendor is required for vendor-scoped coupons.');
            }
        }

        if ($scope === Coupon::SCOPE_TOURS && $tourIds === []) {
            abort(422, 'Select at least one tour for tour-scoped coupons.');
        }

        return [
            'code' => Coupon::normalizeCode((string) $validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'minimum_booking_amount' => $validated['minimum_booking_amount'] ?? null,
            'maximum_discount_amount' => $validated['maximum_discount_amount'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_limit_per_user' => $validated['usage_limit_per_user'] ?? null,
            'scope' => $scope,
            'vendor_profile_id' => $vendorId,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $request->user()->id,
            'tour_ids' => $tourIds,
        ];
    }
}
