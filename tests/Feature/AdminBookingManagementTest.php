<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\City;
use App\Models\State;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_manual_booking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->createTourPackage();

        $response = $this->actingAs($admin)->post('/admin/bookings', $this->bookingPayload($package));

        $response->assertRedirect(route('admin.bookings.index', absolute: false));
        $this->assertDatabaseHas('bookings', ['customer_name' => 'Radha Sharma', 'user_id' => $admin->id]);
    }

    public function test_admin_can_update_a_manual_booking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->createTourPackage();
        $booking = Booking::create($this->bookingPayload($package) + ['user_id' => $admin->id]);

        $response = $this->actingAs($admin)->put("/admin/bookings/{$booking->id}", $this->bookingPayload($package, ['customer_name' => 'Mohan Das']));

        $response->assertRedirect(route('admin.bookings.index', absolute: false));
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'customer_name' => 'Mohan Das']);
    }

    public function test_admin_can_delete_a_manual_booking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->createTourPackage();
        $booking = Booking::create($this->bookingPayload($package) + ['user_id' => $admin->id]);

        $response = $this->actingAs($admin)->delete("/admin/bookings/{$booking->id}");

        $response->assertRedirect();
        $this->assertModelMissing($booking);
    }

    private function bookingPayload(TourPackage $package, array $overrides = []): array
    {
        return array_merge([
            'package_id' => $package->id, 'customer_name' => 'Radha Sharma', 'customer_phone' => '9876543210',
            'customer_email' => 'radha@example.com', 'pickup_address' => 'Delhi Airport',
            'travel_date' => now()->addWeek()->toDateString(), 'total_adults' => 2, 'total_children' => 1,
            'total_amount' => 12000, 'payment_status' => 'pending', 'booking_status' => 'confirmed',
        ], $overrides);
    }

    private function createTourPackage(): TourPackage
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);

        return TourPackage::create([
            'title' => 'Vrindavan Darshan', 'slug' => 'vrindavan-darshan', 'city_id' => $city->id,
            'duration_days' => 2, 'duration_nights' => 1, 'price' => 5000, 'is_active' => true,
        ]);
    }
}
