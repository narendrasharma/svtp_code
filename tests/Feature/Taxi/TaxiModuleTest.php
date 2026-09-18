<?php

namespace Tests\Feature\Taxi;

use App\Models\Setting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin->fresh();
    }

    protected function makeVendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        return $vendor;
    }

    public function test_taxi_module_enabled_by_default(): void
    {
        $manager = app(ModuleManager::class);

        $this->assertTrue($manager->isEnabled(ModuleManager::TAXI));
        $this->assertContains(ModuleManager::TAXI, $manager->active());
    }

    public function test_disabling_taxi_module_blocks_admin_routes(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $manager = app(ModuleManager::class);
        $this->assertTrue($manager->isDisabled(ModuleManager::TAXI));

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.taxi.bookings.index', absolute: false));
        $response->assertNotFound();
    }

    public function test_disabling_taxi_hides_admin_navigation_and_quick_actions(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.dashboard', absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('adminNavigation', fn (Assert $nav) => $nav
                    ->each(fn (Assert $group) => $group->whereNot('key', ModuleManager::TAXI)->etc())
                )
                ->has('quickActions', fn (Assert $actions) => $actions
                    ->each(fn (Assert $action) => $action->whereNot('id', 'taxi-booking')->etc())
                ));
    }

    public function test_vendor_taxi_routes_blocked_when_module_disabled(): void
    {
        Setting::setValue('modules.taxi.enabled', '0');

        $response = $this->actingAs($this->makeVendor())->get(route('vendor.taxi.bookings.index', absolute: false));
        $response->assertNotFound();
    }
}
