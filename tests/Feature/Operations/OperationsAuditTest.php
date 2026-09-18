<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingSource;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsAuditTest extends TestCase
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

    public function test_login_and_logout_are_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'secret-admin-1']);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'secret-admin-1'])->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'auth.login')->where('actor_user_id', $admin->id)->exists());

        $this->post(route('logout'))->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'auth.logout')->where('actor_user_id', $admin->id)->exists());
    }

    public function test_failed_login_audited_without_password(): void
    {
        $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'super-secret-password']);

        $log = ActivityLog::where('event', 'auth.failed_login')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('super-secret-password', json_encode($log->toArray()));
    }

    public function test_staff_role_change_is_audited(): void
    {
        $super = $this->staffWithRole('super-admin');
        $member = User::factory()->create(['role' => 'admin']);

        $this->actingAs($super)->put(route('admin.staff.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'roles' => ['support-agent'],
        ])->assertRedirect();

        $this->assertTrue(ActivityLog::where('event', 'staff.roles_changed')->where('subject_id', $member->id)->exists());
    }

    public function test_tour_moderation_is_audited(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $tour = TourPackage::factory()->create(['moderation_status' => 'pending_review']);

        $this->actingAs($manager)->post(route('admin.packages.approve', $tour))->assertRedirect();

        $this->assertTrue(ActivityLog::where('module', 'tours')->where('subject_id', $tour->id)->exists());
    }

    public function test_lead_assignment_and_status_are_audited(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $assignee = User::factory()->create(['role' => 'admin']);
        $lead = Lead::factory()->create(['status' => 'new']);

        $this->actingAs($manager)->post(route('admin.leads.assign', $lead), ['assigned_to' => $assignee->id])->assertRedirect();
        $this->actingAs($manager)->patch(route('admin.leads.status', $lead), ['status' => 'contacted'])->assertRedirect();

        $this->assertTrue(ActivityLog::where('event', 'like', 'lead.%')->where('subject_id', $lead->id)->exists());
    }

    public function test_booking_create_payment_and_reschedule_are_audited(): void
    {
        $exec = $this->staffWithRole('booking-executive');
        $tour = TourPackage::factory()->create(['price' => 5000, 'is_active' => true, 'moderation_status' => 'approved']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($exec)->post(route('admin.bookings.store'), [
            'package_id' => $tour->id,
            'user_id' => $customer->id,
            'customer_name' => 'Audit Guest',
            'customer_phone' => '9840044444',
            'travel_date' => now()->addDays(10)->toDateString(),
            'total_adults' => 2,
            'total_children' => 0,
            'source' => 'phone',
        ])->assertRedirect();

        $booking = Booking::sole();
        $this->assertTrue(ActivityLog::where('module', 'bookings')->where('subject_id', $booking->id)->exists());

        $this->actingAs($exec)->post(route('admin.bookings.payments.store', $booking), [
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'booking_payment.created')->exists());

        $this->actingAs($exec)->post(route('admin.bookings.reschedule.store', $booking), [
            'new_travel_date' => now()->addDays(20)->toDateString(),
            'reason' => 'Customer requested later dates.',
        ])->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'booking_reschedule.created')->exists());
    }

    public function test_refund_and_withdrawal_are_audited(): void
    {
        $finance = $this->staffWithRole('finance-manager');
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::factory()->create(['user_id' => $vendorUser->id, 'is_active' => true]);
        $tour = TourPackage::factory()->forVendor($profile)->create(['price' => 8000, 'is_active' => true, 'moderation_status' => 'approved']);

        $booking = app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Refund Guest', 'email' => 'refund@example.com', 'phone' => '9876543210'],
            null,
            BookingSource::Admin,
            $finance,
        );
        app(BookingService::class)->markPayment($booking, PaymentStatus::Paid, $finance);

        $this->actingAs($finance)->post(route('admin.bookings.refunds.store', $booking->refresh()), [
            'amount' => 500,
            'reason' => 'Partial goodwill refund.',
        ])->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'booking_refund.created')->exists());
    }

    public function test_settings_and_module_toggle_are_audited(): void
    {
        $super = $this->staffWithRole('super-admin');

        $this->actingAs($super)->post(route('admin.settings.basic.update'), [
            'site_name' => 'Audit Probe Site',
            'site_tagline' => 'Probe tagline',
            'copyright_text' => 'Probe rights',
        ])->assertRedirect();
        $this->assertTrue(ActivityLog::where('module', 'system')->whereIn('event', ['setting.created', 'setting.updated'])->exists());

        $this->actingAs($super)->patch(route('admin.modules.update', 'tours'), ['enabled' => true])->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'module.toggled')->exists());
    }

    public function test_impersonation_start_and_end_are_audited(): void
    {
        $super = $this->staffWithRole('super-admin');
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($super)->post(route('admin.users.impersonate', $customer))->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'impersonation.started')->exists());

        $this->post(route('impersonation.stop'))->assertRedirect();
        $this->assertTrue(ActivityLog::where('event', 'impersonation.ended')->exists());
    }

    public function test_sensitive_fields_are_sanitized(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'initial-secret-1']);

        $user->update(['password' => 'rotated-secret-2', 'remember_token' => 'tok123']);

        $log = ActivityLog::where('subject_type', User::class)->where('subject_id', $user->id)->latest()->first();
        $this->assertNotNull($log);
        $payload = json_encode([$log->old_values, $log->new_values]);
        $this->assertStringNotContainsString('rotated-secret-2', $payload);
        $this->assertStringNotContainsString('tok123', $payload);
    }

    public function test_audit_log_access_control(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('admin.activity-logs.index'))->assertForbidden();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $this->actingAs($vendor)->get(route('admin.activity-logs.index'))->assertForbidden();

        $agent = $this->staffWithRole('support-agent');
        $this->actingAs($agent)->get(route('admin.activity-logs.index'))->assertForbidden();

        $super = $this->staffWithRole('super-admin');
        $this->actingAs($super)->get(route('admin.activity-logs.index'))->assertOk();
    }

    public function test_quotation_lifecycle_is_audited(): void
    {
        $sales = $this->staffWithRole('booking-executive');

        $this->actingAs($sales)->post(route('admin.quotations.store'), [
            'service_type' => 'tour',
            'currency' => 'INR',
            'valid_until' => now()->addDays(15)->toDateString(),
            'items' => [
                ['item_type' => 'manual', 'description' => 'Darshan tour', 'quantity' => 2, 'unit_price' => 2500],
            ],
        ])->assertRedirect();

        $quotation = Quotation::sole();
        $this->assertTrue(ActivityLog::where('module', 'crm')->where('subject_id', $quotation->id)->exists());
    }

    public function test_scheduler_markers_do_not_spam_audit(): void
    {
        $booking = Booking::factory()->create();

        $before = ActivityLog::count();
        $booking->forceFill(['last_travel_reminder_at' => now()])->save();

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_setting_model_audit(): void
    {
        Setting::setValue('probe.key', 'probe-value');

        $this->assertTrue(
            ActivityLog::where('event', 'setting.created')->orWhere(fn ($q) => $q->where('event', 'setting.updated'))->exists()
        );
    }
}
