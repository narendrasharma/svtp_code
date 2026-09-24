<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiBookingAdminBreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    public function test_taxi_booking_create_uses_new_booking_breadcrumb(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(ModuleManager::class)->setEnabled('taxi', true);
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');

        $response = $this->actingAs($admin)
            ->get(route('admin.taxi.bookings.create', absolute: false));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Taxi/Bookings/Form')
                ->where('adminBreadcrumbs.3.label', 'New Taxi Booking'));
    }
}
