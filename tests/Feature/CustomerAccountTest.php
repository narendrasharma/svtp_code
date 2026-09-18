<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer'], $overrides));
    }

    // ---------- Roles & authentication ----------

    public function test_customer_is_redirected_by_role_after_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->customer();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->post(route('logout'));

        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('account.dashboard', absolute: false));
    }

    public function test_customer_cannot_access_the_admin_area(): void
    {
        $this->actingAs($this->customer())
            ->get(route('admin.bookings.index'))
            ->assertForbidden();
    }

    public function test_guests_cannot_open_the_account_area(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
        $this->get(route('account.bookings.index'))->assertRedirect(route('login'));
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    // ---------- Dashboard ----------

    public function test_dashboard_stats_only_count_the_signed_in_customer(): void
    {
        $mine = $this->customer();
        Booking::factory()->create(['user_id' => $mine->id, 'travel_date' => now()->addWeek()->toDateString()]);
        Booking::factory()->create([
            'user_id' => $mine->id, 'travel_date' => now()->subWeek()->toDateString(), 'booking_status' => 'completed',
        ]);
        Booking::factory()->create(); // someone else's

        $this->actingAs($mine)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Dashboard')
                ->where('stats', ['total' => 2, 'upcoming' => 1, 'pending' => 1, 'completed' => 1])
                ->has('recentBookings', 2)
            );
    }

    // ---------- My bookings ----------

    public function test_customer_sees_only_own_bookings_with_scoped_filters(): void
    {
        $mine = $this->customer();
        $wanted = Booking::factory()->create(['user_id' => $mine->id, 'customer_name' => 'Searchable Trip']);
        Booking::factory()->create(['customer_name' => 'Stranger Trip']);

        $response = $this->actingAs($mine)->get(route('account.bookings.index'))->assertOk();
        $this->assertCount(1, $response->viewData('page')['props']['bookings']['data']);

        $this->actingAs($mine)
            ->get(route('account.bookings.index', ['search' => substr($wanted->booking_reference_id, 0, 8)]))
            ->assertInertia(fn (Assert $page) => $page->has('bookings.data', 1));

        $this->actingAs($mine)
            ->get(route('account.bookings.index', ['search' => 'Stranger Trip']))
            ->assertInertia(fn (Assert $page) => $page->has('bookings.data', 0));
    }

    public function test_customer_booking_detail_and_cross_customer_protection(): void
    {
        $mine = $this->customer();
        $other = $this->customer();
        $booking = Booking::factory()->create(['user_id' => $mine->id]);
        $foreign = Booking::factory()->create(['user_id' => $other->id]);

        $this->actingAs($mine)
            ->get(route('account.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Bookings/Show')
                ->where('booking.id', $booking->id)
            );

        $this->actingAs($mine)->get(route('account.bookings.show', $foreign))->assertForbidden();
        $this->actingAs($other)->get(route('account.bookings.show', $booking))->assertForbidden();
    }

    // ---------- Invoice ----------

    public function test_invoice_policy_owner_stranger_admin(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('booking.invoice', $booking))->assertOk();
        $this->actingAs($stranger)->get(route('booking.invoice', $booking))->assertForbidden();
        $this->actingAs($admin)->get(route('booking.invoice', $booking))->assertOk();
    }

    // ---------- Payment security ----------

    public function test_no_public_route_can_mark_a_booking_paid(): void
    {
        $owner = $this->customer();
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        // The old unsigned confirm endpoint is gone entirely.
        $this->actingAs($owner)->post("/bookings/{$booking->id}/confirm")->assertNotFound();
        $this->assertSame('unpaid', $booking->refresh()->payment_status->value);

        $this->assertFalse(Route::has('booking.confirm'));
    }

    public function test_customer_cannot_mutate_status_or_payment_directly(): void
    {
        $owner = $this->customer();
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        // No customer-facing status/payment mutation routes exist.
        $this->actingAs($owner)->patch("/account/bookings/{$booking->id}/status", ['booking_status' => 'completed'])->assertNotFound();
        $this->assertSame('pending', $booking->refresh()->booking_status->value);
    }

    // ---------- Cancellation ----------

    public function test_customer_can_request_cancellation_for_own_booking(): void
    {
        $owner = $this->customer();
        $booking = Booking::factory()->confirmed()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('account.cancellation-requests.store', $booking), ['reason' => 'Plans changed'])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_cancellation_requests', [
            'booking_id' => $booking->id,
            'user_id' => $owner->id,
            'status' => 'pending',
            'reason' => 'Plans changed',
        ]);
        // Requesting never flips the booking itself.
        $this->assertSame('confirmed', $booking->refresh()->booking_status->value);
    }

    public function test_cancellation_request_rejects_foreign_completed_and_duplicate_requests(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        $booking = Booking::factory()->confirmed()->create(['user_id' => $owner->id]);
        $done = Booking::factory()->create(['user_id' => $owner->id, 'booking_status' => 'completed']);

        $this->actingAs($stranger)
            ->post(route('account.cancellation-requests.store', $booking), [])
            ->assertForbidden();
        $this->assertDatabaseCount('booking_cancellation_requests', 0);

        $this->actingAs($owner)
            ->post(route('account.cancellation-requests.store', $done), [])
            ->assertSessionHasErrors('booking');

        $this->actingAs($owner)->post(route('account.cancellation-requests.store', $booking), []);
        $this->actingAs($owner)
            ->post(route('account.cancellation-requests.store', $booking), [])
            ->assertSessionHasErrors('booking');
        $this->assertSame(1, $booking->cancellationRequests()->count());
    }

    public function test_admin_can_review_cancellation_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = $this->customer();
        $booking = Booking::factory()->confirmed()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->post(route('account.cancellation-requests.store', $booking), ['reason' => 'Sick']);
        $cancellation = $booking->cancellationRequests()->sole();

        // Admin detail page surfaces the request.
        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page
                ->has('booking.cancellation_requests', 1)
                ->where('booking.cancellation_requests.0.reason', 'Sick')
            );

        // Approve flows through the valid transition; payment untouched.
        $this->actingAs($admin)
            ->patch(route('admin.bookings.cancellation.approve', [$booking, $cancellation]), ['review_note' => 'Ok'])
            ->assertRedirect();
        $this->assertSame('cancelled', $booking->refresh()->booking_status->value);
        $this->assertSame('approved', $cancellation->refresh()->status->value);
        $this->assertSame('unpaid', $booking->payment_status->value);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id, 'from_status' => 'confirmed', 'to_status' => 'cancelled',
        ]);

        // Reject path keeps the booking alive and allows asking again later.
        $second = Booking::factory()->confirmed()->create(['user_id' => $owner->id]);
        $this->actingAs($owner)->post(route('account.cancellation-requests.store', $second), []);
        $rejected = $second->cancellationRequests()->sole();
        $this->actingAs($admin)
            ->patch(route('admin.bookings.cancellation.reject', [$second, $rejected]))
            ->assertRedirect();
        $this->assertSame('rejected', $rejected->refresh()->status->value);
        $this->assertSame('confirmed', $second->refresh()->booking_status->value);
    }

    // ---------- Profile ----------

    public function test_profile_change_keeps_historical_booking_snapshot(): void
    {
        $owner = $this->customer(['name' => 'Old Name', 'phone' => '9000000001']);
        Booking::factory()->create(['user_id' => $owner->id, 'customer_name' => 'Old Name', 'customer_phone' => '9000000001']);

        $this->actingAs($owner)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $owner->email, 'phone' => '9111111111'])
            ->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertSame('New Name', $owner->name);
        $this->assertSame('9111111111', $owner->phone);
        $this->assertDatabaseHas('bookings', ['user_id' => $owner->id, 'customer_name' => 'Old Name', 'customer_phone' => '9000000001']);
    }

    // ---------- Policy & base path ----------

    public function test_booking_policy_matrix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = $this->customer();
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Booking::class));
        $this->assertFalse(Gate::forUser($owner)->allows('viewAny', Booking::class));
        $this->assertTrue(Gate::forUser($owner)->allows('viewInvoice', $booking));
        $this->assertTrue(Gate::forUser($owner)->allows('requestCancellation', $booking));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $booking));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $booking));
    }

    public function test_account_routes_live_under_the_app_base_path(): void
    {
        $this->assertSame('/account', route('account.dashboard', absolute: false));
        $this->assertSame('/account/bookings', route('account.bookings.index', absolute: false));

        $this->actingAs($this->customer())->get(route('account.dashboard'))->assertOk();
    }

    public function test_legacy_my_bookings_redirects_to_the_account_area(): void
    {
        $this->actingAs($this->customer())
            ->get(route('booking.history'))
            ->assertRedirect(route('account.bookings.index'));
    }
}
