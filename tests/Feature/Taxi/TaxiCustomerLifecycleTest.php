<?php

namespace Tests\Feature\Taxi;

use App\Models\TaxiBooking;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TaxiCustomerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_customer_sees_only_owned_safe_taxi_cards(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $vehicleType = VehicleType::factory()->create(['name' => 'Customer Sedan']);
        TaxiBooking::factory()->create(['customer_user_id' => $owner->id, 'vehicle_type_id' => $vehicleType->id]);
        TaxiBooking::factory()->create(['customer_user_id' => $other->id]);

        $response = $this->actingAs($owner)->get(route('account.taxi.bookings.index', absolute: false));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Account/Taxi/Index')
            ->has('bookings.data', 1)
            ->where('bookings.data.0.vehicle.name', 'Customer Sedan')
            ->missing('bookings.data.0.customer_user_id')
            ->missing('bookings.data.0.vendor_profile_id'));
    }

    public function test_customer_detail_exposes_server_action_flags_and_safe_money_dto(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $booking = TaxiBooking::factory()->create([
            'customer_user_id' => $owner->id,
            'total_amount' => '1250.00',
            'currency' => 'INR',
            'pricing_snapshot' => ['breakdown' => ['base_fare' => '1000.00', 'tax' => '250.00'], 'calculated_at' => now()->toISOString()],
        ]);

        $response = $this->actingAs($owner)->get(route('account.taxi.changes.show', $booking, absolute: false));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Account/Taxi/Show')
            ->where('booking.reference', $booking->reference)
            ->where('booking.pricing.total.amount', '1250.00')
            ->where('actions.can_cancel', false)
            ->where('actions.can_reschedule', false)
            ->where('actions.can_review', false)
            ->missing('booking.customer_user_id')
            ->missing('booking.vendor_profile_id'));
    }
}
