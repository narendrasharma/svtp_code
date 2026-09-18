<?php

namespace Tests\Feature\Crm;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\TourBlackoutDate;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BookingRescheduleTest extends TestCase
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

    protected function booking(?TourPackage $package = null): Booking
    {
        $package ??= TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);

        return app(BookingService::class)->createTourBooking(
            $package,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Mover', 'phone' => '9880011111'],
            null,
            BookingSource::Admin,
        );
    }

    public function test_valid_new_date_succeeds_with_history(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->booking();
        $oldDate = $booking->travel_date->toDateString();
        $newDate = now()->addWeeks(2)->toDateString();

        $response = $this->actingAs($ops)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => $newDate,
            'reason' => 'Customer requested date shift',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame($newDate, $booking->travel_date->toDateString());
        $row = $booking->reschedules()->sole();
        $this->assertSame($oldDate, $row->old_travel_date->toDateString());
        $this->assertSame($newDate, $row->new_travel_date->toDateString());
        $this->assertDatabaseHas('booking_status_histories', ['booking_id' => $booking->id]);
    }

    public function test_blackout_date_rejected(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);
        $booking = $this->booking($package);
        $blocked = now()->addWeeks(3)->toDateString();

        TourBlackoutDate::create(['tour_package_id' => $package->id, 'date' => $blocked, 'reason' => 'Festival closure']);

        $this->actingAs($ops)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => $blocked,
            'reason' => 'Trying a blocked date',
        ])->assertInvalid('travel_date');

        $this->assertNotSame($blocked, $booking->refresh()->travel_date->toDateString());
        $this->assertSame(0, $booking->reschedules()->count());
    }

    public function test_price_increase_adds_amount_due(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);
        $booking = $this->booking($package); // 2 adults × 5000 = 10000
        $this->assertSame(10000.0, (float) $booking->total_amount);

        $package->update(['price' => 6000]);

        $this->actingAs($ops)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => now()->addWeeks(2)->toDateString(),
            'reason' => 'Shift with new pricing',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame(12000.0, (float) $booking->total_amount);

        $summary = app(BookingPaymentService::class)->summary($booking);
        $this->assertSame(12000.0, $summary['due']);

        $row = $booking->reschedules()->sole();
        $this->assertSame(2000.0, (float) $row->price_difference);
    }

    public function test_price_drop_reduces_due_without_auto_refund(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);
        $booking = $this->booking($package);

        app(BookingPaymentService::class)->recordPayment($booking, 10000, PaymentMethod::Cash, $ops);
        $this->assertSame('paid', $booking->refresh()->payment_status->value);

        $package->update(['price' => 4000]);

        $this->actingAs($ops)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => now()->addWeeks(2)->toDateString(),
            'reason' => 'Cheaper season',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame(8000.0, (float) $booking->total_amount);
        // No automatic refund row was created.
        $this->assertSame(0, $booking->refunds()->count());
        // Due reflects the credit position.
        $this->assertSame(-2000.0, app(BookingPaymentService::class)->summary($booking)['due']);
        // Payment status untouched by the move.
        $this->assertSame('paid', $booking->payment_status->value);
    }

    public function test_cancelled_booking_cannot_reschedule(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->booking();
        $booking->update(['booking_status' => 'cancelled']);

        $this->actingAs($ops)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => now()->addWeeks(2)->toDateString(),
            'reason' => 'Too late',
        ])->assertInvalid('travel_date');
    }

    public function test_unauthorized_staff_blocked(): void
    {
        // support-agent is read-only and has no bookings.reschedule.
        $support = $this->staffWithRole('support-agent');
        $booking = $this->booking();

        $this->actingAs($support)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => now()->addWeeks(2)->toDateString(),
            'reason' => 'Nope',
        ])->assertForbidden();
    }

    public function test_cancellation_flow_unchanged(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = $this->booking();
        $booking->update(['user_id' => $customer->id]);

        $this->actingAs($customer)->post(route('account.cancellation-requests.store', $booking), [
            'reason' => 'Change of plans',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_cancellation_requests', ['booking_id' => $booking->id, 'status' => 'pending']);
        // Reschedule rows are a separate workflow.
        $this->assertSame(0, $booking->reschedules()->count());
    }
}
