<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingSource;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Services\AdminDigestService;
use App\Services\BookingService;
use App\Services\CancellationService;
use App\Services\LeadService;
use App\Services\OperationalReminders;
use App\Services\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsNotificationsTest extends TestCase
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

    protected function adminAudience(): void
    {
        User::factory()->create(['role' => 'admin']);
    }

    public function test_booking_created_alerts_admins_once(): void
    {
        $this->adminAudience();
        $tour = TourPackage::factory()->create(['price' => 5000, 'is_active' => true, 'moderation_status' => 'approved']);

        app(BookingService::class)->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Alert Guest', 'email' => 'alert@example.com', 'phone' => '9876543210'],
            null,
            BookingSource::Website,
        );

        $this->assertDatabaseHas('notifications', ['type' => AdminAlert::class]);
        $alerts = DB::table('notifications')
            ->where('type', AdminAlert::class)
            ->where('data', 'like', '%booking_created%')
            ->count();
        $this->assertSame(1, $alerts);
    }

    public function test_cancellation_request_alerts_admins(): void
    {
        $this->adminAudience();
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'booking_status' => 'confirmed']);

        app(CancellationService::class)->request($booking, $customer, 'Plans changed.');

        $alerts = DB::table('notifications')
            ->where('type', AdminAlert::class)
            ->where('data', 'like', '%cancellation_requested%')
            ->count();
        $this->assertSame(1, $alerts);
    }

    public function test_lead_created_alert_is_opt_in(): void
    {
        $this->adminAudience();

        // Default off: no admin spam from manual lead creation.
        app(LeadService::class)->createLead(['name' => 'Quiet Lead', 'phone' => '9811111111']);
        $this->assertSame(
            0,
            DB::table('notifications')->where('type', AdminAlert::class)->where('data', 'like', '%lead_created%')->count()
        );

        Setting::setValue('ops.notify_lead_created', '1');
        app(LeadService::class)->createLead(['name' => 'Loud Lead', 'phone' => '9822222222']);
        $this->assertSame(
            1,
            DB::table('notifications')->where('type', AdminAlert::class)->where('data', 'like', '%lead_created%')->count()
        );
    }

    public function test_high_priority_ticket_uses_distinct_alert(): void
    {
        $this->adminAudience();
        $requester = User::factory()->create(['role' => 'customer']);

        app(SupportService::class)->open(['subject' => 'Urgent help', 'body' => 'Please help now.', 'priority' => 'urgent'], $requester);
        app(SupportService::class)->open(['subject' => 'Normal query', 'body' => 'Just asking.'], $requester);

        $db = DB::table('notifications')->where('type', AdminAlert::class);
        $this->assertSame(1, (clone $db)->where('data', 'like', '%support_ticket_high_priority%')->count());
        $this->assertSame(1, (clone $db)->where('data', 'like', '%support_ticket_opened%')->count());
    }

    public function test_campaign_schedule_then_dispatch(): void
    {
        $admin = $this->staffWithRole('super-admin');
        User::factory()->count(2)->create(['role' => 'customer']);

        $campaign = Campaign::factory()->create(['channel' => 'in_app', 'status' => 'draft']);

        // Past dates are rejected.
        $this->actingAs($admin)->post(route('admin.campaigns.schedule', $campaign), [
            'scheduled_at' => now()->subHour()->toDateTimeString(),
        ])->assertInvalid('scheduled_at');

        $this->actingAs($admin)->post(route('admin.campaigns.schedule', $campaign), [
            'scheduled_at' => now()->addHour()->toDateTimeString(),
        ])->assertRedirect();
        $this->assertSame('scheduled', $campaign->refresh()->status);

        // Scheduler ignores future campaigns.
        $this->assertSame(0, app(OperationalReminders::class)->dispatchDueCampaigns());

        // Due campaigns dispatch through the queued chunked sender.
        $campaign->update(['scheduled_at' => now()->subMinute()]);
        $this->assertSame(1, app(OperationalReminders::class)->dispatchDueCampaigns());
        $this->assertSame('completed', $campaign->refresh()->status);
    }

    public function test_scheduled_campaign_shows_timestamp_and_locks_editing(): void
    {
        $admin = $this->staffWithRole('super-admin');

        $campaign = Campaign::factory()->create(['channel' => 'in_app', 'status' => 'draft']);
        $at = now()->addHours(3)->startOfMinute();

        $this->actingAs($admin)->post(route('admin.campaigns.schedule', $campaign), [
            'scheduled_at' => $at->toDateTimeString(),
        ])->assertRedirect();

        $campaign->refresh();
        $this->assertSame('scheduled', $campaign->status);
        $this->assertEquals($at->timestamp, $campaign->scheduled_at->timestamp);

        // Show page exposes the scheduled timestamp and timezone.
        $response = $this->actingAs($admin)->get(route('admin.campaigns.show', $campaign));
        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertNotNull($props['campaign']['scheduled_at']);
        $this->assertNotEmpty($props['timezone']);
        $this->assertFalse($props['canSchedule']);

        // Scheduled campaigns cannot be edited (backend authoritative).
        $this->actingAs($admin)->get(route('admin.campaigns.edit', $campaign))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.campaigns.update', $campaign), ['name' => 'Renamed'])->assertForbidden();
    }

    public function test_terminal_campaigns_cannot_be_scheduled(): void
    {
        $admin = $this->staffWithRole('super-admin');

        foreach (['cancelled', 'completed', 'failed', 'sending'] as $status) {
            $campaign = Campaign::factory()->create(['channel' => 'in_app', 'status' => $status]);

            $this->actingAs($admin)->post(route('admin.campaigns.schedule', $campaign), [
                'scheduled_at' => now()->addHour()->toDateTimeString(),
            ])->assertForbidden();

            $this->assertSame($status, $campaign->refresh()->status);
        }
    }

    public function test_digest_off_by_default_and_sends_when_daily(): void
    {
        $this->adminAudience();
        Lead::factory()->create(['status' => 'new']);

        // Off by default: scheduler sends nothing.
        $this->assertSame(0, app(AdminDigestService::class)->maybeSendDaily());

        Setting::setValue('ops.admin_digest_frequency', 'daily');
        $this->assertSame(1, app(AdminDigestService::class)->maybeSendDaily());

        $digest = DB::table('notifications')
            ->where('data', 'like', '%admin_digest%')
            ->count();
        $this->assertSame(1, $digest);
    }

    public function test_cleanup_prunes_only_old_read_notifications(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $oldRead = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AdminAlert::class,
            'data' => ['kind' => 'x'],
            'read_at' => now()->subDays(200),
            'created_at' => now()->subDays(200),
        ]);
        $recentRead = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AdminAlert::class,
            'data' => ['kind' => 'x'],
            'read_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);
        $oldUnread = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AdminAlert::class,
            'data' => ['kind' => 'x'],
            'created_at' => now()->subDays(200),
        ]);

        $this->artisan('ops:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recentRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $oldUnread->id]);

        // Audit and financial records are never touched by cleanup.
        $this->assertGreaterThan(0, ActivityLog::count());
    }
}
