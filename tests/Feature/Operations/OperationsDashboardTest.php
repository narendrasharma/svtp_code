<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorProfile;
use App\Services\BookingPaymentService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
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

    protected function vendorBooking(User $finance, float $total = 10000): Booking
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::factory()->create(['user_id' => $vendorUser->id, 'is_active' => true]);
        $tour = TourPackage::factory()->forVendor($profile)->create(['price' => $total, 'is_active' => true, 'moderation_status' => 'approved']);

        $booking = app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Dash Guest', 'email' => 'dash@example.com', 'phone' => '9876543210'],
            null,
            BookingSource::Admin,
            $finance,
        );
        app(BookingService::class)->markPayment($booking, PaymentStatus::Paid, $finance);

        return $booking->refresh();
    }

    public function test_dashboard_counts_and_financial_semantics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create(['role' => 'customer']);
        $this->vendorBooking($admin);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $ops = $response->viewData('page')['props']['ops'] ?? null;
        $this->assertNotNull($ops);

        // Counts.
        $this->assertSame(2, $ops['kpis']['customers']);
        $this->assertSame(1, $ops['kpis']['bookings_total']);

        // Financial semantics: GBV is customer-paid totals; commission and
        // earnings are separate slices. GBV must equal their sum here.
        $gbv = (float) $ops['kpis']['gross_booking_value'];
        $commission = (float) $ops['kpis']['platform_commission'];
        $earnings = (float) $ops['kpis']['vendor_earnings'];
        $this->assertGreaterThan(0, $gbv);
        $this->assertGreaterThan(0, $commission);
        $this->assertGreaterThan(0, $earnings);
        $this->assertEqualsWithDelta($gbv, $commission + $earnings, 0.01);

        // Labels never conflate GBV with revenue.
        $response->assertDontSee('Revenue', false);
    }

    public function test_outstanding_balance_and_collected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->vendorBooking($admin, 10000);

        app(BookingPaymentService::class)->recordPayment(
            $booking,
            4000,
            PaymentMethod::Cash,
            $admin,
        );

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $ops = $response->viewData('page')['props']['ops'];

        // Outstanding = total − paid + refunded (derived, never stored).
        $expected = number_format((float) $booking->total_amount - 4000, 2, '.', '');
        $this->assertSame($expected, $ops['kpis']['outstanding_balance']);
        $this->assertSame('4000.00', $ops['kpis']['payments_collected']);
    }

    public function test_lead_counts_and_conversion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Lead::factory()->count(3)->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'won']);

        $ops = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props']['ops'];

        $this->assertSame(4, $ops['kpis']['leads_total']);
        $this->assertSame(1, $ops['kpis']['leads_won']);
        $this->assertSame(25.0, $ops['kpis']['lead_conversion_rate']);
    }

    public function test_date_range_preset_applies(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', ['preset' => 'today']));
        $response->assertOk();
        $this->assertSame('today', $response->viewData('page')['props']['analyticsRange']['preset']);
    }

    public function test_needs_attention_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        LeadFollowUp::factory()->create(['due_at' => now()->subDays(2)]);
        VendorApplication::factory()->create(['status' => 'pending']);
        SupportTicket::factory()->create(['status' => 'open', 'priority' => 'urgent']);

        $ops = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props']['ops'];
        $items = collect($ops['needs_attention'])->keyBy('key');

        $this->assertSame(1, $items['overdue_followups']['count']);
        $this->assertSame(1, $items['vendor_applications']['count']);
        $this->assertSame(1, $items['high_priority_tickets']['count']);
        $this->assertNotEmpty($items['overdue_followups']['url']);
    }

    public function test_module_disabled_hides_tour_widgets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('modules.tours.enabled', '0');

        $ops = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props']['ops'];

        $this->assertFalse($ops['tours_enabled']);
        $tourItem = collect($ops['needs_attention'])->firstWhere('module', 'tours');
        $this->assertNull($tourItem);
    }

    public function test_permission_filtering_hides_finance_and_restricted_items(): void
    {
        // Booking executives have no reports.view: financial KPIs stay null
        // and finance-gated attention items disappear.
        $exec = $this->staffWithRole('booking-executive');

        $ops = $this->actingAs($exec)->get(route('admin.dashboard'))->viewData('page')['props']['ops'];

        $this->assertFalse($ops['finance_visible']);
        $this->assertNull($ops['kpis']['gross_booking_value']);
        $this->assertNull($ops['kpis']['outstanding_balance']);
        $this->assertNotNull($ops['kpis']['customers']);

        $keys = collect($ops['needs_attention'])->pluck('key')->all();
        $this->assertNotContains('failed_jobs', $keys);
        $this->assertNotContains('pending_withdrawals', $keys);
    }
}
