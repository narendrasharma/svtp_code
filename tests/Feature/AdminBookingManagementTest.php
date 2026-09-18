<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function tourPackage(array $overrides = []): TourPackage
    {
        return TourPackage::factory()->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true],
            $overrides
        ));
    }

    public function test_admin_can_open_the_booking_index_with_filters(): void
    {
        $this->actingAsAdmin();
        Booking::factory()->create();

        $this->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Bookings/Index')
                ->has('bookings.data', 1)
                ->has('packages')
                ->has('statuses')
                ->has('paymentStatuses')
            );
    }

    public function test_admin_can_search_and_filter_bookings(): void
    {
        $this->actingAsAdmin();
        $wanted = Booking::factory()->create(['customer_name' => 'Radha Sharma']);
        Booking::factory()->confirmed()->create(['customer_name' => 'Mohan Das']);

        $this->get(route('admin.bookings.index', ['search' => 'Radha']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $wanted->id)
            );

        $this->get(route('admin.bookings.index', ['status' => 'confirmed']))
            ->assertInertia(fn (Assert $page) => $page->has('bookings.data', 1));

        $this->get(route('admin.bookings.index', ['payment_status' => 'paid']))
            ->assertInertia(fn (Assert $page) => $page->has('bookings.data', 0));
    }

    public function test_admin_can_open_a_booking_detail_page(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create();

        $this->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Bookings/Show')
                ->where('booking.id', $booking->id)
                ->has('booking.status_histories')
            );
    }

    public function test_admin_can_create_a_manual_booking_with_server_pricing(): void
    {
        $this->actingAsAdmin();
        $package = $this->tourPackage();

        // A forged browser total must be ignored: 2 adults + 1 child at 5000.
        $response = $this->post(route('admin.bookings.store'), [
            'package_id' => $package->id, 'customer_name' => 'Radha Sharma', 'customer_phone' => '9876543210',
            'customer_email' => 'radha@example.com', 'pickup_address' => 'Delhi Airport',
            'travel_date' => now()->addWeek()->toDateString(), 'total_adults' => 2, 'total_children' => 1,
            'total_amount' => 1, 'booking_status' => 'confirmed', 'payment_status' => 'unpaid',
        ]);

        $booking = Booking::sole();
        $response->assertRedirect(route('admin.bookings.show', $booking));
        $this->assertSame(12500.0, (float) $booking->total_amount);
        $this->assertSame(5000.0, (float) $booking->base_price);
        $this->assertSame('INR', $booking->currency);
        $this->assertSame('Radha Sharma', $booking->customer_name);
        $this->assertSame('9876543210', $booking->customer_phone);
        $this->assertSame('radha@example.com', $booking->customer_email);
        $this->assertNull($booking->user_id);
        $this->assertSame('admin', $booking->source->value);
        $this->assertSame('tour', $booking->product_type);
        $this->assertMatchesRegularExpression('/^BK-\d{4}-\d{6}$/', $booking->booking_reference_id);
        $this->assertSame(1, $booking->statusHistories()->count());

        $this->get(route('admin.bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('flash.message', 'Manual booking created.'));
    }

    public function test_admin_can_update_booking_and_payment_status_with_history(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->create();

        $this->patch(route('admin.bookings.status', $booking), [
            'booking_status' => 'confirmed', 'payment_status' => 'paid', 'note' => 'Paid over the phone',
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->booking_status->value);
        $this->assertSame('paid', $booking->payment_status->value);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'payment_from' => 'unpaid',
            'payment_to' => 'paid',
        ]);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->confirmed()->create();

        $this->patchJson(route('admin.bookings.status', $booking), ['booking_status' => 'pending'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_status');

        $this->assertSame('confirmed', $booking->refresh()->booking_status->value);

        $this->patchJson(route('admin.bookings.status', $booking), ['booking_status' => 'archived'])
            ->assertUnprocessable();
    }

    public function test_admin_can_cancel_a_booking_but_not_hard_delete_it(): void
    {
        $this->actingAsAdmin();
        $booking = Booking::factory()->confirmed()->create();

        $this->patch(route('admin.bookings.status', $booking), ['booking_status' => 'cancelled'])
            ->assertRedirect();
        $this->assertSame('cancelled', $booking->refresh()->booking_status->value);
        $this->assertModelExists($booking);

        // Hard deletion is gone: the show route only allows GET, and the
        // edit/update/destroy route names no longer exist. Records are
        // cancelled, never deleted.
        $this->assertFalse(Route::has('admin.bookings.destroy'));
        $this->assertFalse(Route::has('admin.bookings.edit'));
        $this->assertFalse(Route::has('admin.bookings.update'));
        $this->delete("/admin/bookings/{$booking->id}")->assertStatus(405);
        $this->get("/admin/bookings/{$booking->id}/edit")->assertNotFound();
        $this->assertModelExists($booking);
    }

    public function test_booking_management_requires_an_admin(): void
    {
        $booking = Booking::factory()->create();

        $this->get(route('admin.bookings.index'))->assertRedirect(route('login'));
        $this->post(route('admin.bookings.store'), [])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->get(route('admin.bookings.index'))->assertForbidden();
        $this->patch(route('admin.bookings.status', $booking), ['booking_status' => 'confirmed'])->assertForbidden();
    }
}
