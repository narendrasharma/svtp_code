<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingPaymentService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsReportsTest extends TestCase
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

    protected function vendorBooking(User $actor, float $price = 12000): Booking
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::factory()->create(['user_id' => $vendorUser->id, 'is_active' => true]);
        $tour = TourPackage::factory()->forVendor($profile)->create(['price' => $price, 'is_active' => true, 'moderation_status' => 'approved']);

        $booking = app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 1, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Report Guest', 'email' => 'report@example.com', 'phone' => '9876543210'],
            null,
            BookingSource::Admin,
            $actor,
        );
        app(BookingService::class)->markPayment($booking, PaymentStatus::Paid, $actor);

        return $booking->refresh();
    }

    public function test_booking_report_scoped_by_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->vendorBooking($admin);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'bookings']));
        $response->assertOk();

        $rows = $response->viewData('page')['props']['rows'];
        $this->assertNotEmpty($rows);
        $this->assertSame($booking->booking_reference_id, $rows[0]['booking']);

        // Impossible status filter scopes to nothing.
        $empty = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'bookings', 'status' => 'no-such-status']));
        $this->assertSame([], $empty->viewData('page')['props']['rows']);
    }

    public function test_finance_report_semantics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->vendorBooking($admin, 12000);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'commission']));
        $rows = $response->viewData('page')['props']['rows'];

        $this->assertNotEmpty($rows);
        $row = $rows[0];
        $this->assertSame($booking->booking_reference_id, $row['booking']);
        // Commission + earnings reconstruct the gross value.
        $this->assertEqualsWithDelta(
            (float) $row['gross_value'],
            (float) $row['platform_commission'] + (float) $row['vendor_earning'],
            0.01
        );
    }

    public function test_vendor_report_shows_own_earnings_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->vendorBooking($admin);

        $rows = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'vendors']))->viewData('page')['props']['rows'];

        $this->assertNotEmpty($rows);
        $row = $rows[0];
        $this->assertArrayHasKey('vendor', $row);
        $this->assertArrayHasKey('recorded_earnings', $row);
        // No platform-internal or private banking fields.
        $this->assertArrayNotHasKey('platform_commission_amount', $row);
        $this->assertArrayNotHasKey('account_number', $row);
    }

    public function test_csv_export_requires_permission_and_has_no_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->vendorBooking($admin);
        Lead::factory()->create(['status' => 'new']);

        $agent = $this->staffWithRole('support-agent');
        $this->actingAs($agent)->get(route('admin.reports.export', ['tab' => 'bookings']))->assertForbidden();

        $response = $this->actingAs($admin)->get(route('admin.reports.export', ['tab' => 'leads']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('reference', strtolower($body));
        foreach (['password', 'account_number', 'aadhaar', 'token'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $body);
        }
    }

    public function test_payment_and_lead_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->vendorBooking($admin);
        app(BookingPaymentService::class)->recordPayment($booking, 1000, PaymentMethod::Cash, $admin);
        Lead::factory()->create(['status' => 'new']);

        $payments = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'payments']))->viewData('page')['props']['rows'];
        $this->assertNotEmpty($payments);
        $this->assertArrayHasKey('reference', $payments[0]);

        $leads = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'leads']))->viewData('page')['props']['rows'];
        $this->assertNotEmpty($leads);
    }
}
