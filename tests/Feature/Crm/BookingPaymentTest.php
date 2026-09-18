<?php

namespace Tests\Feature\Crm;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingPaymentService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
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

    protected function paidTotalBooking(float $price = 5000): Booking
    {
        $package = TourPackage::factory()->create(['price' => $price, 'discounted_price' => null, 'is_active' => true]);

        return app(BookingService::class)->createTourBooking(
            $package,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Payer', 'phone' => '9870000000'],
            null,
            BookingSource::Admin,
        );
    }

    public function test_partial_payment_leaves_correct_due(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->paidTotalBooking(); // total 5000

        $response = $this->actingAs($ops)->post(route('admin.bookings.payments.store', $booking), [
            'amount' => 3000,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame('partially_paid', $booking->payment_status->value);

        $summary = app(BookingPaymentService::class)->summary($booking);
        $this->assertSame(5000.0, $summary['total']);
        $this->assertSame(3000.0, $summary['paid']);
        $this->assertSame(2000.0, $summary['due']);

        $payment = BookingPayment::sole();
        $this->assertMatchesRegularExpression('/^PAY-\d{4}-\d{6}$/', $payment->reference);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'payment_to' => 'partially_paid',
        ]);
    }

    public function test_second_payment_settles_to_paid(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->paidTotalBooking();

        $service = app(BookingPaymentService::class);
        $service->recordPayment($booking, 3000, PaymentMethod::Cash, $ops);
        $service->recordPayment($booking->refresh(), 2000, PaymentMethod::Upi, $ops);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status->value);
        $this->assertSame(0.0, $service->summary($booking)['due']);
    }

    public function test_overpayment_is_blocked(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->paidTotalBooking();

        $this->actingAs($ops)->post(route('admin.bookings.payments.store', $booking), [
            'amount' => 6000,
            'payment_method' => 'cash',
        ])->assertInvalid('amount');

        $this->assertSame(0, BookingPayment::count());
        $this->assertSame('unpaid', $booking->refresh()->payment_status->value);
    }

    public function test_payment_references_are_unique(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->paidTotalBooking();

        $service = app(BookingPaymentService::class);
        $first = $service->recordPayment($booking, 1000, PaymentMethod::Cash, $ops);
        $second = $service->recordPayment($booking->refresh(), 1000, PaymentMethod::Cash, $ops);

        $this->assertNotSame($first->reference, $second->reference);
    }

    public function test_unauthorized_staff_cannot_record_payment(): void
    {
        // content-manager has no payments.record.
        $staff = $this->staffWithRole('content-manager');
        $booking = $this->paidTotalBooking();

        $this->actingAs($staff)->post(route('admin.bookings.payments.store', $booking), [
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertForbidden();

        $this->assertSame(0, BookingPayment::count());
    }

    public function test_customer_cannot_forge_payment(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = $this->paidTotalBooking();
        $booking->update(['user_id' => $customer->id]);

        $this->actingAs($customer)->post(route('admin.bookings.payments.store', $booking), [
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertForbidden();

        $this->assertSame(0, BookingPayment::count());
    }

    public function test_receipt_shows_balance_without_commission(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->paidTotalBooking();
        $payment = app(BookingPaymentService::class)->recordPayment($booking, 3000, PaymentMethod::Cash, $ops);

        $response = $this->actingAs($ops)->get(route('admin.bookings.payments.receipt', [$booking, $payment]));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString($payment->reference, $html);
        $this->assertStringContainsString('2,000.00', $html);
        $this->assertStringNotContainsStringIgnoringCase('commission', $html);
    }

    public function test_fully_paid_booking_credits_ledger_once(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        // Vendor-owned tour so there is an earning to credit.
        $vendor = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true, 'vendor_profile_id' => $profile->id]);

        $booking = app(BookingService::class)->createTourBooking(
            $package,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Payer', 'phone' => '9870000000'],
            null,
            BookingSource::Admin,
        );

        app(BookingPaymentService::class)->recordPayment($booking, (float) $booking->total_amount, PaymentMethod::Cash, $ops);

        $this->assertSame('paid', $booking->refresh()->payment_status->value);
        $this->assertDatabaseHas('vendor_ledger_entries', [
            'booking_id' => $booking->id,
            'reference' => "booking-earning:{$booking->id}",
        ]);
    }

    public function test_cancelled_booking_rejects_payment(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = $this->paidTotalBooking();
        $booking->update(['booking_status' => 'cancelled']);

        $this->actingAs($ops)->post(route('admin.bookings.payments.store', $booking), [
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertInvalid('booking');
    }
}
