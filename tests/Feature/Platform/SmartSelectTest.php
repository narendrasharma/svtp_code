<?php

namespace Tests\Feature\Platform;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SmartSelectTest extends TestCase
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

    public function test_select_options_require_authentication(): void
    {
        $this->getJson(route('admin.select-options', ['type' => 'tours']))->assertUnauthorized();
    }

    public function test_select_options_require_permission_per_type(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole('content-manager');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $staff = $staff->fresh();

        // content-manager: tours yes, bookings/users no.
        $this->actingAs($staff)->getJson(route('admin.select-options', ['type' => 'tours']))->assertOk();
        $this->actingAs($staff)->getJson(route('admin.select-options', ['type' => 'bookings']))->assertForbidden();
        $this->actingAs($staff)->getJson(route('admin.select-options', ['type' => 'users']))->assertForbidden();
    }

    public function test_select_options_search_and_shape(): void
    {
        TourPackage::factory()->create(['title' => 'Vrindavan Holi Special', 'is_active' => true]);
        TourPackage::factory()->create(['title' => 'Agra Day Trip', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.select-options', ['type' => 'tours', 'search' => 'Holi']));

        $response->assertOk();
        $options = $response->json('options');
        $this->assertCount(1, $options);
        $this->assertSame(['value', 'label', 'meta'], array_keys($options[0]));
        $this->assertSame('Vrindavan Holi Special', $options[0]['label']);
    }

    public function test_select_options_users_vendors_bookings(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Selectable Customer']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendorUser->id, 'business_name' => 'Selectable Travels', 'is_active' => true]);
        $booking = Booking::factory()->create(['customer_name' => 'Selectable Customer']);

        $admin = $this->admin();

        $users = $this->actingAs($admin)->getJson(route('admin.select-options', ['type' => 'users', 'search' => 'Selectable Customer']))->json('options');
        $this->assertContains($customer->id, collect($users)->pluck('value')->all());

        $vendors = $this->actingAs($admin)->getJson(route('admin.select-options', ['type' => 'vendors', 'search' => 'Selectable Travels']))->json('options');
        $this->assertSame('Selectable Travels', $vendors[0]['label']);

        $bookings = $this->actingAs($admin)->getJson(route('admin.select-options', ['type' => 'bookings', 'search' => substr($booking->booking_reference_id, 0, 10)]))->json('options');
        $this->assertContains($booking->id, collect($bookings)->pluck('value')->all());
    }

    public function test_select_options_unknown_type_rejected(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('admin.select-options', ['type' => 'nope']))
            ->assertStatus(422);
    }

    public function test_select_options_empty_when_module_disabled(): void
    {
        TourPackage::factory()->create(['title' => 'Vrindavan Holi Special', 'is_active' => true]);
        Setting::setValue('modules.tours.enabled', '0');

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.select-options', ['type' => 'tours']));

        $response->assertOk()->assertJsonPath('options', []);
    }

    public function test_booking_form_still_submits_package_value(): void
    {
        $admin = $this->admin();
        $package = TourPackage::factory()->create(['is_active' => true, 'price' => 5000]);

        $this->actingAs($admin)->get(route('admin.bookings.create'))->assertOk();

        $response = $this->actingAs($admin)->post(route('admin.bookings.store'), [
            'package_id' => $package->id,
            'customer_name' => 'Smart Select',
            'customer_phone' => '9876543210',
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 2,
            'total_children' => 0,
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'package_id' => $package->id,
            'customer_name' => 'Smart Select',
        ]);
    }

    public function test_smart_select_components_exist(): void
    {
        $this->assertFileExists(resource_path('js/Components/SmartSelect.vue'));
        $this->assertFileExists(resource_path('js/Components/SmartMultiSelect.vue'));

        $single = file_get_contents(resource_path('js/Components/SmartSelect.vue'));
        $this->assertStringContainsString('role="combobox"', $single);
        $this->assertStringContainsString('fetchOptions', $single);

        $multi = file_get_contents(resource_path('js/Components/SmartMultiSelect.vue'));
        $this->assertStringContainsString('aria-multiselectable', $multi);
        $this->assertStringContainsString('Clear all', $multi);
    }
}
