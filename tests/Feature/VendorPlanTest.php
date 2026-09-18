<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorPlan;
use App\Models\VendorProfile;
use App\Services\VendorApprovalService;
use App\Services\VendorEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 11: vendor plans + entitlements.
 */
class VendorPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    protected function vendor(): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return $user->refresh();
    }

    protected function entitlements(): VendorEntitlementService
    {
        return app(VendorEntitlementService::class);
    }

    public function test_default_plan_assigned_on_approval(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['role' => 'customer']);
        $application = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        $profile = app(VendorApprovalService::class)->approve($application, $admin->id);

        $this->assertNotNull($profile->vendor_plan_id);
        $this->assertTrue($profile->plan->is_default);
        $this->assertDatabaseHas('vendor_plan_assignments', ['vendor_profile_id' => $profile->id]);
    }

    public function test_existing_vendor_safety_net_gets_default(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorProfile->update(['vendor_plan_id' => null]);
        $vendor->vendorProfile->planAssignments()->delete();

        $this->assertNull($vendor->vendorProfile->refresh()->vendor_plan_id);

        $plan = $this->entitlements()->ensureDefaultPlan($vendor->vendorProfile);

        $this->assertNotNull($plan);
        $this->assertNotNull($vendor->vendorProfile->refresh()->vendor_plan_id);
    }

    public function test_admin_can_create_plan(): void
    {
        $this->actingAs($this->admin())->post(route('admin.vendor-plans.store'), [
            'name' => 'Free Plan',
            'description' => 'For tests',
            'is_active' => true,
            'features' => [
                ['key' => 'max_active_tours', 'value_type' => 'integer', 'integer_value' => 3],
                ['key' => 'max_coupons', 'value_type' => 'integer', 'integer_value' => 2],
                ['key' => 'featured_listing', 'value_type' => 'boolean', 'boolean_value' => false],
            ],
        ])->assertRedirect();

        $plan = VendorPlan::where('slug', 'free-plan')->first();
        $this->assertNotNull($plan);
        $this->assertSame(3, (int) $plan->features->firstWhere('key', 'max_active_tours')->integer_value);
    }

    public function test_only_one_default_plan(): void
    {
        $admin = $this->admin();
        $first = VendorPlan::where('is_default', true)->first();
        $this->assertNotNull($first);

        $second = VendorPlan::factory()->create(['is_default' => false]);

        $this->actingAs($admin)->put(route('admin.vendor-plans.update', $second), [
            'name' => $second->name,
            'is_active' => true,
            'is_default' => true,
        ])->assertRedirect();

        $this->assertTrue($second->refresh()->is_default);
        $this->assertFalse($first->refresh()->is_default);
    }

    public function test_inactive_plan_cannot_be_assigned(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->post(route('admin.vendor-profiles.plan.assign', $vendor->vendorProfile), [
            'vendor_plan_id' => $plan->id,
        ])->assertSessionHasErrors('vendor_plan_id');

        $this->assertNotSame($plan->id, (int) $vendor->vendorProfile->refresh()->vendor_plan_id);
    }

    public function test_vendor_cannot_change_own_plan(): void
    {
        $vendor = $this->vendor();
        $other = VendorPlan::factory()->create();

        $this->actingAs($vendor)->post(route('admin.vendor-profiles.plan.assign', $vendor->vendorProfile), [
            'vendor_plan_id' => $other->id,
        ])->assertForbidden();
    }

    public function test_admin_can_assign_plan(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();

        $this->actingAs($admin)->post(route('admin.vendor-profiles.plan.assign', $vendor->vendorProfile), [
            'vendor_plan_id' => $plan->id, 'note' => 'Upgrade for test',
        ])->assertRedirect();

        $this->assertSame($plan->id, (int) $vendor->vendorProfile->refresh()->vendor_plan_id);
        $this->assertDatabaseHas('vendor_plan_assignments', [
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'vendor_plan_id' => $plan->id,
        ]);
    }

    public function test_tour_limit_server_enforced_on_submit(): void
    {
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();
        $plan->features()->where('key', 'max_active_tours')->update(['value_type' => 'integer', 'integer_value' => 1]);
        $vendor->vendorProfile->update(['vendor_plan_id' => $plan->id]);

        $active = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'is_active' => true, 'moderation_status' => 'approved',
        ]);
        $this->assertNotNull($active->id);

        $draft = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'is_active' => false, 'moderation_status' => 'draft',
        ]);

        // Draft creation itself is allowed; submission is the gate.
        $this->actingAs($vendor)->post(route('vendor.tours.submit', $draft))
            ->assertSessionHasErrors('tour');
    }

    public function test_drafts_do_not_count_but_submission_does(): void
    {
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();
        $plan->features()->where('key', 'max_active_tours')->update(['value_type' => 'integer', 'integer_value' => 1]);
        $vendor->vendorProfile->update(['vendor_plan_id' => $plan->id]);

        // Three drafts are fine under a limit of 1 active tour.
        $city = City::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($vendor)->post(route('vendor.tours.store'), [
                'title' => "Draft {$i} ".uniqid(),
                'city_id' => $city->id,
                'duration_days' => 2,
                'price' => 1000,
            ])->assertRedirect();
        }

        $this->assertSame(0, $this->entitlements()->activeTourUsage($vendor->vendorProfile->refresh()));
    }

    public function test_coupon_limit_server_enforced(): void
    {
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();
        $plan->features()->where('key', 'max_coupons')->update(['value_type' => 'integer', 'integer_value' => 1]);
        $vendor->vendorProfile->update(['vendor_plan_id' => $plan->id]);

        $this->actingAs($vendor)->post(route('vendor.coupons.store'), [
            'code' => 'FIRST', 'name' => 'First', 'discount_type' => 'fixed',
            'discount_value' => 100, 'scope' => 'vendor',
        ])->assertRedirect();

        $this->actingAs($vendor)->post(route('vendor.coupons.store'), [
            'code' => 'SECOND', 'name' => 'Second', 'discount_type' => 'fixed',
            'discount_value' => 100, 'scope' => 'vendor',
        ])->assertSessionHasErrors('code');
    }

    public function test_downgrade_keeps_content_but_blocks_new(): void
    {
        $vendor = $this->vendor();
        $big = VendorPlan::factory()->create();
        $big->features()->where('key', 'max_active_tours')->update(['value_type' => 'integer', 'integer_value' => 10]);
        $vendor->vendorProfile->update(['vendor_plan_id' => $big->id]);

        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'is_active' => true, 'moderation_status' => 'approved',
        ]);

        $small = VendorPlan::factory()->create();
        $small->features()->where('key', 'max_active_tours')->update(['value_type' => 'integer', 'integer_value' => 1]);

        $this->actingAs($this->admin())->post(route('admin.vendor-profiles.plan.assign', $vendor->vendorProfile), [
            'vendor_plan_id' => $small->id,
        ])->assertRedirect();

        // Content remains published.
        $this->assertTrue($tour->refresh()->is_active);

        // But another submit is blocked at the limit.
        $draft = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'is_active' => false, 'moderation_status' => 'draft',
        ]);
        $this->actingAs($vendor)->post(route('vendor.tours.submit', $draft))->assertSessionHasErrors('tour');
    }

    public function test_unlimited_entitlement_allows_more(): void
    {
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();
        $plan->features()->where('key', 'max_active_tours')->update([
            'value_type' => 'unlimited', 'integer_value' => null,
        ]);
        $vendor->vendorProfile->update(['vendor_plan_id' => $plan->id]);

        $this->assertNull($this->entitlements()->limitFor($vendor->vendorProfile->refresh(), 'max_active_tours'));

        for ($i = 0; $i < 3; $i++) {
            TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
                'is_active' => true, 'moderation_status' => 'approved',
            ]);
        }

        $draft = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'is_active' => false, 'moderation_status' => 'draft',
            'city_id' => TourPackage::factory()->create(['is_active' => false, 'moderation_status' => 'draft'])->city_id,
            'price' => 1000, 'overview' => 'Overview here',
        ]);

        $this->actingAs($vendor)->post(route('vendor.tours.submit', $draft))->assertSessionHasNoErrors();
    }

    public function test_boolean_entitlement(): void
    {
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();
        $vendor->vendorProfile->update(['vendor_plan_id' => $plan->id]);

        $this->assertFalse($this->entitlements()->canUseFeaturedListing($vendor->vendorProfile->refresh()));

        $plan->features()->where('key', VendorPlan::KEY_FEATURED_LISTING)->update([
            'value_type' => 'boolean', 'boolean_value' => true,
        ]);

        $this->assertTrue($this->entitlements()->canUseFeaturedListing($vendor->vendorProfile->refresh()));
    }

    public function test_impersonated_vendor_cannot_bypass_plan(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();
        $plan->features()->where('key', 'max_coupons')->update(['value_type' => 'integer', 'integer_value' => 0]);
        $vendor->vendorProfile->update(['vendor_plan_id' => $plan->id]);

        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendor))->assertRedirect();

        // Effective vendor limits apply while impersonating.
        $this->post(route('vendor.coupons.store'), [
            'code' => 'IMPERSON', 'name' => 'Imperson', 'discount_type' => 'fixed',
            'discount_value' => 50, 'scope' => 'vendor',
        ])->assertSessionHasErrors('code');

        // Plan assignment stays admin-only (impersonated vendor is not admin).
        $other = VendorPlan::factory()->create();
        $this->post(route('admin.vendor-profiles.plan.assign', $vendor->vendorProfile), [
            'vendor_plan_id' => $other->id,
        ])->assertForbidden();

        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_plan_with_history_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $plan = $vendor->vendorProfile->plan;

        $this->assertNotNull($plan);

        $this->actingAs($admin)->delete(route('admin.vendor-plans.destroy', $plan))
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseHas('vendor_plans', ['id' => $plan->id]);
    }

    public function test_addon_limit_enforced(): void
    {
        $vendor = $this->vendor();
        $plan = VendorPlan::factory()->create();
        $plan->features()->where('key', 'max_addons_per_tour')->update(['value_type' => 'integer', 'integer_value' => 1]);
        $vendor->vendorProfile->update(['vendor_plan_id' => $plan->id]);

        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'is_active' => true, 'moderation_status' => 'approved',
        ]);

        $this->actingAs($vendor)->post(route('vendor.tours.addons.store', $tour), [
            'name' => 'First', 'pricing_type' => 'fixed', 'price' => 100,
        ])->assertRedirect();

        $this->actingAs($vendor)->post(route('vendor.tours.addons.store', $tour), [
            'name' => 'Second', 'pricing_type' => 'fixed', 'price' => 100,
        ])->assertSessionHasErrors('name');
    }
}
