<?php

namespace Tests\Feature\Crm;

use App\Enums\BookingSource;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OfflineBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function staffWithRole(string $role): User
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff->fresh();
    }

    protected function tourPackage(array $overrides = []): TourPackage
    {
        return TourPackage::factory()->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true],
            $overrides
        ));
    }

    protected function deskPayload(TourPackage $package, array $overrides = []): array
    {
        return array_merge([
            'package_id' => $package->id,
            'customer_name' => 'Desk Guest',
            'customer_phone' => '9840044444',
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 2,
            'total_children' => 0,
            'source' => 'phone',
        ], $overrides);
    }

    public function test_admin_creates_phone_booking_with_source(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->tourPackage();

        $response = $this->actingAs($admin)->post(route('admin.bookings.store'), $this->deskPayload($package));

        $booking = Booking::sole();
        $response->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame(BookingSource::Phone->value, $booking->source->value);
        $this->assertSame(10000.0, (float) $booking->total_amount);
    }

    public function test_walk_in_source_and_status_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->tourPackage();

        $this->actingAs($admin)->post(route('admin.bookings.store'), $this->deskPayload($package, ['source' => 'walk_in']));

        $this->assertSame(BookingSource::WalkIn->value, Booking::sole()->source->value);
    }

    public function test_existing_customer_selection_links_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->tourPackage();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin)->post(route('admin.bookings.store'), $this->deskPayload($package, ['user_id' => $customer->id]));

        $booking = Booking::sole();
        $this->assertSame($customer->id, $booking->user_id);
        // Linked customers get notified.
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customer->id]);
    }

    public function test_non_customer_link_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->tourPackage();
        $vendor = User::factory()->create(['role' => 'vendor']);

        $this->actingAs($admin)->post(route('admin.bookings.store'), $this->deskPayload($package, ['user_id' => $vendor->id]))
            ->assertStatus(422);
    }

    public function test_arbitrary_source_strings_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->tourPackage();

        $this->actingAs($admin)->post(route('admin.bookings.store'), $this->deskPayload($package, ['source' => 'carrier-pigeon']))
            ->assertInvalid('source');
    }

    public function test_vendor_can_create_own_product_booking(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);
        $package = $this->tourPackage(['vendor_profile_id' => $profile->id]);

        $response = $this->actingAs($vendor)->post(route('vendor.bookings.store'), [
            'package_id' => $package->id,
            'customer_name' => 'Counter Guest',
            'customer_phone' => '9850055555',
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 1,
        ]);

        $booking = Booking::sole();
        $response->assertRedirect(route('vendor.bookings.show', $booking));
        $this->assertSame(BookingSource::Vendor->value, $booking->source->value);
        $this->assertSame($profile->id, $booking->vendor_profile_id);
        $this->assertSame('unpaid', $booking->payment_status->value);
    }

    public function test_vendor_cannot_book_another_vendors_product(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        $other = User::factory()->create(['role' => 'vendor']);
        $otherProfile = VendorProfile::factory()->create(['user_id' => $other->id, 'is_active' => true]);
        $package = $this->tourPackage(['vendor_profile_id' => $otherProfile->id]);

        $this->actingAs($vendor)->post(route('vendor.bookings.store'), [
            'package_id' => $package->id,
            'customer_name' => 'Sneaky',
            'customer_phone' => '9860066666',
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 1,
        ])->assertForbidden();

        $this->assertSame(0, Booking::count());
    }

    public function test_vendor_cannot_book_admin_owned_tour(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);
        $package = $this->tourPackage(['vendor_profile_id' => null]);

        $this->actingAs($vendor)->post(route('vendor.bookings.store'), [
            'package_id' => $package->id,
            'customer_name' => 'Sneaky',
            'customer_phone' => '9860066666',
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 1,
        ])->assertForbidden();
    }

    public function test_server_pricing_stays_authoritative_on_desk(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->tourPackage();

        $this->actingAs($admin)->post(route('admin.bookings.store'), $this->deskPayload($package, [
            'total_amount' => 1,
            'base_price' => 1,
        ]));

        // Client-sent totals are not even accepted fields — snapshot wins.
        $this->assertSame(10000.0, (float) Booking::sole()->total_amount);
    }

    public function test_reservation_desk_renders(): void
    {
        $ops = $this->staffWithRole('operations-manager');

        $response = $this->actingAs($ops)->get(route('admin.bookings.desk'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('packages', $props);
        $this->assertArrayHasKey('sources', $props);
        $this->assertArrayHasKey('paymentMethods', $props);
    }

    public function test_crm_dashboard_renders(): void
    {
        $ops = $this->staffWithRole('operations-manager');

        $response = $this->actingAs($ops)->get(route('admin.crm.index'));
        $response->assertOk();

        $stats = $response->viewData('page')['props']['stats'];
        foreach (['new_leads', 'hot_leads', 'unassigned', 'due_today', 'overdue', 'quotations_sent', 'quotations_accepted', 'won', 'lost'] as $key) {
            $this->assertArrayHasKey($key, $stats);
        }
    }

    public function test_desk_booking_with_first_collection(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = $this->tourPackage();

        $this->actingAs($ops)->post(route('admin.bookings.store'), $this->deskPayload($package, [
            'initial_payment_amount' => 3000,
            'initial_payment_method' => 'cash',
        ]))->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame('partially_paid', $booking->refresh()->payment_status->value);
        $this->assertDatabaseHas('booking_payments', ['booking_id' => $booking->id, 'amount' => 3000]);
    }
}
