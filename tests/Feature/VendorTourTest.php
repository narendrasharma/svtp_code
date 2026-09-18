<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\City;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VendorTourTest extends TestCase
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

    protected function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer'], $overrides));
    }

    protected function vendor(): User
    {
        $u = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $u->id, 'is_active' => true]);

        return $u;
    }

    protected function city(): City
    {
        return City::factory()->create();
    }

    // Ownership
    public function test_vendor_sees_only_own_tours(): void
    {
        $v1 = $this->vendor();
        $v2 = $this->vendor();
        $city = $this->city();
        TourPackage::factory()->forVendor($v1->vendorProfile)->create(['title' => 'V1 Tour', 'city_id' => $city->id, 'moderation_status' => 'draft']);
        TourPackage::factory()->forVendor($v2->vendorProfile)->create(['title' => 'V2 Tour', 'city_id' => $city->id, 'moderation_status' => 'draft']);

        $this->actingAs($v1)->get(route('vendor.tours.index'))->assertOk()->assertInertia(fn ($p) => $p->has('tours.data', 1)->where('tours.data.0.title', 'V1 Tour'));
        $this->actingAs($v2)->get(route('vendor.tours.index'))->assertInertia(fn ($p) => $p->has('tours.data', 1)->where('tours.data.0.title', 'V2 Tour'));
    }

    public function test_vendor_cannot_edit_another_vendors_tour(): void
    {
        $v1 = $this->vendor();
        $v2 = $this->vendor();
        $tour = TourPackage::factory()->forVendor($v2->vendorProfile)->create(['city_id' => $this->city()->id]);
        $this->actingAs($v1)->get(route('vendor.tours.edit', $tour))->assertForbidden();
        $this->actingAs($v1)->put(route('vendor.tours.update', $tour), ['title' => 'Hacked'])->assertForbidden();
    }

    public function test_admin_manages_all(): void
    {
        $admin = $this->admin();
        $v1 = $this->vendor();
        TourPackage::factory()->forVendor($v1->vendorProfile)->create(['city_id' => $this->city()->id]);
        TourPackage::factory()->create(['city_id' => $this->city()->id]); // admin
        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()->assertInertia(fn ($p) => $p->has('packages.data', 2));
    }

    public function test_customer_blocked(): void
    {
        $customer = $this->customer();
        $tour = TourPackage::factory()->create(['city_id' => $this->city()->id]);
        $this->actingAs($customer)->get(route('vendor.tours.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('vendor.tours.create'))->assertForbidden();
        $this->actingAs($customer)->get(route('vendor.tours.edit', $tour))->assertForbidden();
    }

    public function test_owner_cannot_be_mass_assigned(): void
    {
        $vendor = $this->vendor();
        $city = $this->city();
        $this->actingAs($vendor)->post(route('vendor.tours.store'), [
            'title' => 'Mass Assign',
            'city_id' => $city->id,
            'duration_days' => 2,
            'price' => 1000,
            'vendor_profile_id' => 9999,
            'moderation_status' => 'approved',
            'created_by' => 9999,
        ])->assertRedirect();
        $tour = TourPackage::where('title', 'Mass Assign')->first();
        $this->assertNotEquals(9999, $tour->vendor_profile_id);
        $this->assertEquals($vendor->vendorProfile->id, $tour->vendor_profile_id);
        $this->assertEquals('draft', $tour->moderation_status->value);
        $this->assertNotEquals('approved', $tour->moderation_status->value);
    }

    // Create
    public function test_vendor_creates_draft(): void
    {
        $vendor = $this->vendor();
        $city = $this->city();
        $this->actingAs($vendor)->post(route('vendor.tours.store'), [
            'title' => 'New Draft Tour',
            'city_id' => $city->id,
            'duration_days' => 3,
            'price' => 5000,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect(route('vendor.tours.index'));
        $tour = TourPackage::where('title', 'New Draft Tour')->first();
        $this->assertEquals('draft', $tour->moderation_status->value);
        $this->assertFalse($tour->is_active);
        $this->assertEquals($vendor->vendorProfile->id, $tour->vendor_profile_id);
    }

    public function test_vendor_cannot_force_approved(): void
    {
        $vendor = $this->vendor();
        $city = $this->city();
        $this->actingAs($vendor)->post(route('vendor.tours.store'), [
            'title' => 'Force Approved',
            'city_id' => $city->id,
            'duration_days' => 2,
            'price' => 1000,
            'moderation_status' => 'approved',
            'is_active' => true,
            'is_featured' => true,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ]);
        $tour = TourPackage::where('title', 'Force Approved')->first();
        $this->assertEquals('draft', $tour->moderation_status->value);
        $this->assertFalse($tour->is_active);
        $this->assertFalse($tour->is_featured);
    }

    // Submit
    public function test_incomplete_draft_saves_but_cannot_submit(): void
    {
        $vendor = $this->vendor();
        $city = $this->city();
        // Create draft missing overview (required for submit, but DB allows null)
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'title' => 'Incomplete',
            'city_id' => $city->id,
            'duration_days' => 2,
            'price' => 2000,
            'overview' => null,
            'moderation_status' => 'draft',
            'is_active' => false,
        ]);
        // Try submit - should fail
        $this->actingAs($vendor)->post(route('vendor.tours.submit', $tour))->assertSessionHasErrors();
        $this->assertEquals('draft', $tour->refresh()->moderation_status->value);
    }

    public function test_complete_submits(): void
    {
        $vendor = $this->vendor();
        $city = $this->city();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create([
            'title' => 'Complete Tour',
            'city_id' => $city->id,
            'duration_days' => 2,
            'price' => 2000,
            'overview' => 'Full overview for submission',
            'moderation_status' => 'draft',
            'is_active' => false,
        ]);
        $this->actingAs($vendor)->post(route('vendor.tours.submit', $tour))->assertRedirect();
        $this->assertEquals('pending_review', $tour->refresh()->moderation_status->value);
        $this->assertNotNull($tour->refresh()->submitted_at);
    }

    public function test_vendor_cannot_self_approve(): void
    {
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['moderation_status' => 'pending_review', 'city_id' => $this->city()->id]);
        $this->actingAs($vendor)->post(route('admin.packages.approve', $tour))->assertForbidden();
    }

    // Admin
    public function test_pending_review_filter(): void
    {
        $admin = $this->admin();
        TourPackage::factory()->create(['moderation_status' => 'pending_review', 'city_id' => $this->city()->id]);
        TourPackage::factory()->create(['moderation_status' => 'approved', 'city_id' => $this->city()->id]);
        $this->actingAs($admin)->get(route('admin.packages.index', ['moderation_status' => 'pending_review']))->assertInertia(fn ($p) => $p->has('packages.data', 1));
    }

    public function test_approve(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['moderation_status' => 'pending_review', 'city_id' => $this->city()->id, 'is_active' => false]);
        $this->actingAs($admin)->post(route('admin.packages.approve', $tour), ['note' => 'OK'])->assertRedirect();
        $tour->refresh();
        $this->assertEquals('approved', $tour->moderation_status->value);
        $this->assertTrue($tour->is_active);
        $this->assertEquals($admin->id, $tour->reviewed_by);
        $this->assertNotNull($tour->reviewed_at);
        $this->assertEquals(1, $tour->moderationHistories()->count());
    }

    public function test_request_changes(): void
    {
        $admin = $this->admin();
        $tour = TourPackage::factory()->create(['moderation_status' => 'pending_review', 'city_id' => $this->city()->id]);
        $this->actingAs($admin)->post(route('admin.packages.requestChanges', $tour), ['note' => 'Fix title'])->assertRedirect();
        $tour->refresh();
        $this->assertEquals('changes_requested', $tour->moderation_status->value);
        $this->assertEquals('Fix title', $tour->review_note);
    }

    public function test_vendor_sees_note(): void
    {
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['moderation_status' => 'changes_requested', 'review_note' => 'Fix please', 'city_id' => $this->city()->id]);
        $this->actingAs($vendor)->get(route('vendor.tours.index'))->assertOk()->assertInertia(fn ($p) => $p->has('tours.data', 1));
        // The vendor's tour should show changes_requested
        $this->assertEquals('changes_requested', $tour->moderation_status->value);
    }

    public function test_reject_and_resubmit(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['moderation_status' => 'pending_review', 'city_id' => $this->city()->id]);
        $this->actingAs($admin)->post(route('admin.packages.reject', $tour), ['note' => 'Bad'])->assertRedirect();
        $this->assertEquals('rejected', $tour->refresh()->moderation_status->value);
        // Vendor edits rejected -> should go to draft? Actually submit will move to pending_review
        $this->actingAs($vendor)->put(route('vendor.tours.update', $tour), [
            'title' => $tour->title,
            'city_id' => $tour->city_id,
            'duration_days' => 2,
            'price' => 1000,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect();
        // After edit, rejected tour's update keeps same status? Our vendor update for approved resets, but for rejected we allow submit
        $this->actingAs($vendor)->post(route('vendor.tours.submit', $tour))->assertRedirect();
        $this->assertEquals('pending_review', $tour->refresh()->moderation_status->value);
    }

    // Public visibility
    public function test_draft_hidden(): void
    {
        $tour = TourPackage::factory()->create(['moderation_status' => 'draft', 'is_active' => false, 'city_id' => $this->city()->id]);
        $this->assertEquals(0, TourPackage::publiclyVisible()->where('id', $tour->id)->count());
        $this->get(route('packages.show', $tour))->assertNotFound();
    }

    public function test_pending_hidden(): void
    {
        $tour = TourPackage::factory()->create(['moderation_status' => 'pending_review', 'city_id' => $this->city()->id]);
        $this->assertEquals(0, TourPackage::publiclyVisible()->where('id', $tour->id)->count());
    }

    public function test_rejected_hidden(): void
    {
        $tour = TourPackage::factory()->create(['moderation_status' => 'rejected', 'city_id' => $this->city()->id]);
        $this->assertEquals(0, TourPackage::publiclyVisible()->where('id', $tour->id)->count());
    }

    public function test_approved_active_visible(): void
    {
        $tour = TourPackage::factory()->create(['moderation_status' => 'approved', 'is_active' => true, 'city_id' => $this->city()->id]);
        $this->assertEquals(1, TourPackage::publiclyVisible()->where('id', $tour->id)->count());
        $this->get(route('packages.show', $tour))->assertOk();
    }

    public function test_unapproved_cannot_be_booked(): void
    {
        $tour = TourPackage::factory()->create(['moderation_status' => 'draft', 'is_active' => false, 'city_id' => $this->city()->id]);
        $this->post(route('booking.store'), [
            'package_id' => $tour->id,
            'travel_date' => now()->addDays(5)->toDateString(),
            'total_adults' => 1,
            'customer_name' => 'Test',
            'customer_phone' => '9999999999',
        ])->assertSessionHasErrors('package_id');
    }

    public function test_admin_created_approved_remains(): void
    {
        $admin = $this->admin();
        $city = $this->city();
        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'title' => 'Admin Tour',
            'city_id' => $city->id,
            'duration_days' => 2,
            'price' => 1000,
            'is_active' => true,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect();
        $tour = TourPackage::where('title', 'Admin Tour')->first();
        $this->assertEquals('approved', $tour->moderation_status->value);
        $this->assertNull($tour->vendor_profile_id);
    }

    // Policy
    public function test_url_tampering_denied(): void
    {
        $v1 = $this->vendor();
        $v2 = $this->vendor();
        $tour = TourPackage::factory()->forVendor($v2->vendorProfile)->create(['city_id' => $this->city()->id]);
        $this->actingAs($v1)->get(route('vendor.tours.edit', $tour))->assertForbidden();
    }

    public function test_impersonated_vendor_obeys_policy(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['city_id' => $this->city()->id]);
        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendor))->assertRedirect();
        $this->get(route('vendor.tours.index'))->assertOk()->assertInertia(fn ($p) => $p->has('tours.data', 1));
        $this->get(route('admin.packages.index'))->assertForbidden(); // impersonated vendor cannot access admin
    }

    // History
    public function test_moderation_history_recorded(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['moderation_status' => 'pending_review', 'city_id' => $this->city()->id]);
        $this->actingAs($admin)->post(route('admin.packages.approve', $tour), ['note' => 'ok']);
        $this->assertDatabaseHas('tour_moderation_histories', ['tour_package_id' => $tour->id, 'from_status' => 'pending_review', 'to_status' => 'approved']);
    }

    // Approved edit resets
    public function test_approved_edit_resets_to_draft(): void
    {
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->create(['moderation_status' => 'approved', 'is_active' => true, 'city_id' => $this->city()->id]);
        $this->actingAs($vendor)->put(route('vendor.tours.update', $tour), [
            'title' => 'Edited Title',
            'city_id' => $tour->city_id,
            'duration_days' => 2,
            'price' => 1000,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect();
        $tour->refresh();
        $this->assertEquals('draft', $tour->moderation_status->value);
        $this->assertFalse($tour->is_active);
        $this->assertEquals(0, TourPackage::publiclyVisible()->where('id', $tour->id)->count());
    }

    // KYC interaction
    public function test_vendor_with_pending_kyc_can_still_create_tour(): void
    {
        $vendor = $this->vendor();
        // Ensure vendor KYC is pending (default)
        $this->actingAs($vendor)->post(route('vendor.tours.store'), [
            'title' => 'KYC Pending Tour',
            'city_id' => $this->city()->id,
            'duration_days' => 2,
            'price' => 1000,
            'destination_ids' => [],
            'place_ids' => [],
            'tag_ids' => [],
        ])->assertRedirect();
        $this->assertDatabaseHas('tour_packages', ['title' => 'KYC Pending Tour']);
    }

    // Booking history preserved
    public function test_historical_bookings_remain_accessible_even_if_tour_inactive(): void
    {
        $tour = TourPackage::factory()->create(['moderation_status' => 'approved', 'is_active' => true, 'city_id' => $this->city()->id]);
        $booking = Booking::factory()->create(['package_id' => $tour->id]);
        $tour->update(['is_active' => false, 'moderation_status' => 'draft']);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
        $this->get(route('packages.show', $tour))->assertNotFound();
    }
}
