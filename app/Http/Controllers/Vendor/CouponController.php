<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Models\Coupon;
use App\Models\TourPackage;
use App\Services\VendorEntitlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Coupon::class);

        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        $query = Coupon::where('vendor_profile_id', $profile->id)->withCount('redemptions')->latest();

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

        return Inertia::render('Vendor/Coupons/Index', [
            'coupons' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Coupon::class);
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        return Inertia::render('Vendor/Coupons/Form', [
            'coupon' => null,
            'tours' => TourPackage::where('vendor_profile_id', $profile->id)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $this->authorize('create', Coupon::class);
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        // Phase 11: plan coupon caps are server-enforced (active coupons).
        app(VendorEntitlementService::class)->assertCanCreateCoupon($profile->refresh());

        $data = $this->couponData($request, (int) $profile->id);
        $coupon = Coupon::create($data);
        $coupon->tours()->sync($data['tour_ids'] ?? []);

        return redirect()->route('vendor.coupons.index')->with('flash', "Coupon {$coupon->code} created.");
    }

    public function edit(Request $request, Coupon $coupon): Response
    {
        $this->authorize('update', $coupon);
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        abort_unless($coupon->isOwnedByVendorProfile($profile), 403);
        $coupon->load('tours:id');

        return Inertia::render('Vendor/Coupons/Form', [
            'coupon' => [...$coupon->toArray(), 'tour_ids' => $coupon->tours->pluck('id')->all(), 'usage' => $coupon->redemptions()->count()],
            'tours' => TourPackage::where('vendor_profile_id', $profile->id)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function update(StoreCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        abort_unless($coupon->isOwnedByVendorProfile($profile), 403);

        $data = $this->couponData($request, (int) $profile->id);
        $coupon->update($data);
        $coupon->tours()->sync($data['tour_ids'] ?? []);

        return redirect()->route('vendor.coupons.index')->with('flash', "Coupon {$coupon->code} updated. Past bookings are unchanged.");
    }

    public function toggle(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        abort_unless($coupon->isOwnedByVendorProfile($profile), 403);

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('flash', $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('delete', $coupon);
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);
        abort_unless($coupon->isOwnedByVendorProfile($profile), 403);

        if ($coupon->redemptions()->exists()) {
            return back()->withErrors(['coupon' => 'This coupon has redemption history. Deactivate it instead.']);
        }

        $coupon->tours()->detach();
        $coupon->delete();

        return redirect()->route('vendor.coupons.index')->with('flash', 'Coupon deleted.');
    }

    /**
     * Vendors may only own vendor-scoped or own-tour-scoped coupons.
     * Global coupons and foreign vendors/tours are rejected here —
     * never trust the request payload.
     *
     * @return array<string, mixed>
     */
    protected function couponData(StoreCouponRequest $request, int $profileId): array
    {
        $validated = $request->validated();

        if (($validated['scope'] ?? null) === Coupon::SCOPE_GLOBAL) {
            throw ValidationException::withMessages(['scope' => 'Vendors cannot create platform-wide coupons.']);
        }

        if (isset($validated['vendor_profile_id']) && (int) $validated['vendor_profile_id'] !== $profileId) {
            throw ValidationException::withMessages(['vendor_profile_id' => 'You can only create coupons for your own tours.']);
        }

        $scope = $validated['scope'] === Coupon::SCOPE_TOURS ? Coupon::SCOPE_TOURS : Coupon::SCOPE_VENDOR;
        $tourIds = array_values(array_unique(array_map('intval', (array) ($validated['tour_ids'] ?? []))));

        if ($scope === Coupon::SCOPE_TOURS) {
            if ($tourIds === []) {
                throw ValidationException::withMessages(['tour_ids' => 'Select at least one of your tours.']);
            }

            $foreign = TourPackage::whereIn('id', $tourIds)->where('vendor_profile_id', '!=', $profileId)->exists();

            if ($foreign) {
                throw ValidationException::withMessages(['tour_ids' => 'You can only select your own tours.']);
            }
        } else {
            $tourIds = [];
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
            'vendor_profile_id' => $profileId,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $request->user()->id,
            'tour_ids' => $tourIds,
        ];
    }
}
