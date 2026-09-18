<?php

namespace Tests\Feature\Crm;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BookingTimelineTest extends TestCase
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

    public function test_creation_payment_reschedule_and_note_events(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);

        $booking = app(BookingService::class)->createTourBooking(
            $package,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Timeline', 'phone' => '9890099999'],
            null,
            BookingSource::Phone,
            $ops,
        );

        // Creation event.
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'to_status' => 'pending',
            'note' => 'Booking created',
        ]);

        app(BookingPaymentService::class)->recordPayment($booking, 2000, PaymentMethod::Cash, $ops);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'payment_to' => 'partially_paid',
        ]);

        $this->actingAs($ops)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => now()->addWeeks(2)->toDateString(),
            'reason' => 'Date shift',
        ]);

        $this->actingAs($ops)->post(route('admin.bookings.notes.store', $booking), [
            'body' => 'VIP guest, handle with care',
        ]);

        $response = $this->actingAs($ops)->get(route('admin.bookings.show', $booking));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertNotEmpty($props['booking']['status_histories']);
        $this->assertNotEmpty($props['booking']['reschedules']);
        $this->assertNotEmpty($props['booking']['notes']);
        $this->assertNotEmpty($props['booking']['payments']);
    }

    public function test_internal_notes_hidden_from_customer(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer']);
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);

        $booking = app(BookingService::class)->createTourBooking(
            $package,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => $customer->name, 'phone' => '9890099999'],
            $customer,
            BookingSource::Phone,
            $ops,
        );

        app(BookingService::class)->logNote($booking, $ops, 'Internal: margin discussion', true);
        app(BookingService::class)->logNote($booking, $ops, 'Called customer, confirmed pickup', false);

        $response = $this->actingAs($customer)->get(route('account.bookings.show', $booking));
        $response->assertOk();

        $histories = $response->viewData('page')['props']['booking']['status_histories'];
        $notes = collect($histories)->pluck('note')->all();

        $this->assertNotContains('Internal: margin discussion', $notes);
        $this->assertContains('Called customer, confirmed pickup', $notes);
    }

    public function test_internal_booking_notes_hidden_from_customer(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer']);
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);

        $booking = app(BookingService::class)->createTourBooking(
            $package,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => $customer->name, 'phone' => '9890099999'],
            $customer,
            BookingSource::Phone,
            $ops,
        );

        $booking->notes()->create(['author_id' => $ops->id, 'body' => 'Secret internal', 'is_internal' => true]);
        $booking->notes()->create(['author_id' => $ops->id, 'body' => 'Shared pickup info', 'is_internal' => false]);

        $response = $this->actingAs($customer)->get(route('account.bookings.show', $booking));
        $bodies = collect($response->viewData('page')['props']['booking']['notes'])->pluck('body')->all();

        $this->assertNotContains('Secret internal', $bodies);
        $this->assertContains('Shared pickup info', $bodies);
    }
}
