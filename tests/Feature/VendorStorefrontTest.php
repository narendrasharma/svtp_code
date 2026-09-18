<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 11: public vendor storefront.
 */
class VendorStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function vendor(array $overrides = []): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(array_merge(['user_id' => $user->id, 'is_active' => true], $overrides));

        return $user->refresh();
    }

    public function test_approved_active_vendor_storefront_public(): void
    {
        $vendor = $this->vendor(['business_name' => 'Braj Tours']);
        $profile = $vendor->vendorProfile;

        $this->get(route('vendors.show', $profile))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('vendor.business_name', 'Braj Tours')
                ->where('vendor.slug', $profile->slug));
    }

    public function test_inactive_vendor_storefront_hidden(): void
    {
        $vendor = $this->vendor(['is_active' => false]);

        $this->get(route('vendors.show', $vendor->vendorProfile))->assertNotFound();
    }

    public function test_storefront_disabled_vendor_hidden(): void
    {
        $vendor = $this->vendor(['storefront_enabled' => false]);

        $this->get(route('vendors.show', $vendor->vendorProfile))->assertNotFound();
    }

    public function test_public_storefront_exposes_safe_fields_only(): void
    {
        $vendor = $this->vendor([
            'business_name' => 'Safe Travels',
            'address' => 'Secret Street 123',
            'postcode' => '999999',
            'phone' => '9000000001',
            'email' => 'private@example.com',
            'public_phone' => '9111111111',
            'public_email' => 'hello@example.com',
        ]);

        $response = $this->get(route('vendors.show', $vendor->vendorProfile));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']['vendor']);

        $this->assertStringContainsString('Safe Travels', $json);
        $this->assertStringContainsString('9111111111', $json);
        $this->assertStringContainsString('hello@example.com', $json);
        $this->assertStringNotContainsString('Secret Street 123', $json);
        $this->assertStringNotContainsString('999999', $json);
        $this->assertStringNotContainsString('9000000001', $json);
        $this->assertStringNotContainsString('private@example.com', $json);
        $this->assertStringNotContainsString('payout', strtolower($json));
        $this->assertStringNotContainsString('verification_status', $json);
        $this->assertStringNotContainsString('admin_note', $json);
    }

    public function test_only_approved_active_tours_listed(): void
    {
        $vendor = $this->vendor();
        $profile = $vendor->vendorProfile;

        $visible = TourPackage::factory()->forVendor($profile)->create([
            'title' => 'Visible Yatra', 'is_active' => true, 'moderation_status' => 'approved',
        ]);
        TourPackage::factory()->forVendor($profile)->create([
            'title' => 'Draft Yatra', 'is_active' => false, 'moderation_status' => 'draft',
        ]);
        TourPackage::factory()->forVendor($profile)->create([
            'title' => 'Pending Yatra', 'is_active' => false, 'moderation_status' => 'pending_review',
        ]);

        $response = $this->get(route('vendors.show', $profile));
        $response->assertOk();

        $titles = collect($response->viewData('page')['props']['tours']['data'])->pluck('title')->all();
        $this->assertContains('Visible Yatra', $titles);
        $this->assertNotContains('Draft Yatra', $titles);
        $this->assertNotContains('Pending Yatra', $titles);
        $this->assertSame($visible->id, $response->viewData('page')['props']['tours']['data'][0]['id']);
    }

    public function test_verified_badge_only_for_verified_kyc(): void
    {
        $unverified = $this->vendor();
        $response = $this->get(route('vendors.show', $unverified->vendorProfile));
        $response->assertOk();
        $this->assertFalse($response->viewData('page')['props']['vendor']['is_verified']);

        $verifiedUser = User::factory()->create(['role' => 'vendor']);
        $verification = VendorVerification::factory()->create(['user_id' => $verifiedUser->id, 'status' => 'verified', 'verified_at' => now()]);
        VendorProfile::factory()->create([
            'user_id' => $verifiedUser->id, 'is_active' => true, 'vendor_verification_id' => $verification->id,
        ]);

        $response = $this->get(route('vendors.show', $verifiedUser->refresh()->vendorProfile));
        $response->assertOk();
        $this->assertTrue($response->viewData('page')['props']['vendor']['is_verified']);
    }

    public function test_slug_unique_and_seo_props(): void
    {
        $vendor = $this->vendor(['business_name' => 'Vrindavan Walks']);
        $profile = $vendor->vendorProfile;

        $this->assertNotNull($profile->slug);
        $this->assertDatabaseCount('vendor_profiles', 1);

        $other = $this->vendor(['business_name' => 'Vrindavan Walks']);
        $this->assertNotSame($profile->slug, $other->vendorProfile->slug);

        $response = $this->get(route('vendors.show', $profile));
        $response->assertOk();
        $seo = $response->viewData('page')['props']['seo'];
        $this->assertStringContainsString('Vrindavan Walks Tours & Packages', $seo['title']);
        $this->assertSame(route('vendors.show', $profile), $seo['canonical']);
    }

    public function test_vendor_can_update_own_public_fields(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($vendor)->patch(route('vendor.profile.update'), [
            'business_name' => $vendor->vendorProfile->business_name,
            'phone' => '9000000001',
            'email' => 'legal@example.com',
            'country_code' => 'IN',
            'public_description' => 'Braj specialists since 2010.',
            'public_phone' => '9111111111',
            'storefront_enabled' => true,
        ])->assertRedirect();

        $this->assertSame('Braj specialists since 2010.', $vendor->vendorProfile->refresh()->public_description);
    }

    public function test_vendor_cannot_update_plan_or_verification_via_profile(): void
    {
        $vendor = $this->vendor();
        $originalPlan = $vendor->vendorProfile->vendor_plan_id;

        $this->actingAs($vendor)->patch(route('vendor.profile.update'), [
            'business_name' => 'Hacked Name',
            'phone' => '9000000001',
            'email' => 'legal@example.com',
            'country_code' => 'IN',
            'vendor_plan_id' => 9999,
            'verification_status' => 'verified',
            'is_active' => false,
        ])->assertRedirect();

        $profile = $vendor->vendorProfile->refresh();
        $this->assertSame('Hacked Name', $profile->business_name);
        $this->assertSame($originalPlan, $profile->vendor_plan_id);
        $this->assertTrue((bool) $profile->is_active);
    }

    public function test_customer_cannot_access_vendor_plan_admin(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('admin.vendor-plans.index'))->assertForbidden();
    }

    public function test_reviews_show_verified_flag_without_reference(): void
    {
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'is_active' => true, 'moderation_status' => 'approved',
        ]);
        Review::create([
            'package_id' => $tour->id,
            'reviewer_name' => 'Storefront Guest',
            'rating' => 5,
            'comment' => 'Lovely yatra',
            'is_approved' => true,
        ]);

        $response = $this->get(route('vendors.show', $vendor->vendorProfile));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']);
        $this->assertStringContainsString('Lovely yatra', $json);
        $this->assertStringNotContainsString('booking_reference', $json);
    }
}
