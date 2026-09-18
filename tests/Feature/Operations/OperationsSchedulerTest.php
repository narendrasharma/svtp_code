<?php

namespace Tests\Feature\Operations;

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\LeadFollowUp;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Services\OperationalReminders;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsSchedulerTest extends TestCase
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

    public function test_scheduler_heartbeat_updates_and_reports_healthy(): void
    {
        $health = app(SystemHealthService::class);

        $this->assertSame('not_detected', $health->schedulerStatus($health->schedulerLastRunAt())['status']);

        $this->artisan('ops:heartbeat')->assertSuccessful();

        $status = $health->schedulerStatus($health->schedulerLastRunAt());
        $this->assertSame('healthy', $status['status']);
    }

    public function test_stale_heartbeat_reports_warning(): void
    {
        $health = app(SystemHealthService::class);

        Setting::setValue('system.scheduler_last_run_at', now()->subHour()->toDateTimeString());

        $this->assertSame('warning', $health->schedulerStatus($health->schedulerLastRunAt())['status']);
    }

    public function test_queue_heartbeat_distinguishes_worker_state(): void
    {
        $health = app(SystemHealthService::class);

        // Sync driver: synchronous, never an error.
        $this->assertSame('sync', $health->queueStatus('sync', null)['status']);

        // Database driver with no heartbeat: worker not detected.
        $this->assertSame('not_detected', $health->queueStatus('database', null)['status']);

        // Heartbeat job stamps the marker when a worker processes it
        // (sync queue in tests runs it inline).
        $this->artisan('ops:queue-heartbeat')->assertSuccessful();
        $this->assertSame('healthy', $health->queueStatus('database', $health->queueLastRunAt())['status']);
    }

    public function test_due_followup_notifies_assignee_once(): void
    {
        $assignee = User::factory()->create(['role' => 'admin']);
        $followUp = LeadFollowUp::factory()->create([
            'assigned_to' => $assignee->id,
            'due_at' => now()->subMinutes(5),
        ]);

        $result = app(OperationalReminders::class)->remindFollowUps();

        $this->assertSame(1, $result['due']);
        $this->assertNotNull($followUp->refresh()->reminder_sent_at);
        $this->assertSame(1, $assignee->notifications()->count());

        // Second tick: no repeat.
        $again = app(OperationalReminders::class)->remindFollowUps();
        $this->assertSame(0, $again['due']);
        $this->assertSame(1, $assignee->notifications()->count());
    }

    public function test_overdue_followup_escalates_without_spam(): void
    {
        $assignee = User::factory()->create(['role' => 'admin']);
        $followUp = LeadFollowUp::factory()->create([
            'assigned_to' => $assignee->id,
            'due_at' => now()->subDays(3),
        ]);

        app(OperationalReminders::class)->remindFollowUps();

        $this->assertNotNull($followUp->refresh()->overdue_reminder_sent_at);
        $firstCount = $assignee->notifications()->count();
        $this->assertSame(1, $firstCount);

        app(OperationalReminders::class)->remindFollowUps();
        $this->assertSame($firstCount, $assignee->notifications()->count());
    }

    public function test_quotation_expires_with_exact_status_rules(): void
    {
        $open = Quotation::factory()->create(['status' => 'sent', 'valid_until' => now()->subDay()->toDateString()]);
        $accepted = Quotation::factory()->create(['status' => 'accepted', 'valid_until' => now()->subDay()->toDateString()]);
        $converted = Quotation::factory()->create(['status' => 'converted', 'valid_until' => now()->subDay()->toDateString()]);
        $draft = Quotation::factory()->create(['status' => 'draft', 'valid_until' => now()->subDay()->toDateString()]);

        $result = app(OperationalReminders::class)->expireQuotations();

        $this->assertSame(1, $result['expired']);
        $this->assertSame('expired', $open->refresh()->status);
        $this->assertSame('accepted', $accepted->refresh()->status);
        $this->assertSame('converted', $converted->refresh()->status);
        $this->assertSame('draft', $draft->refresh()->status);
    }

    public function test_expiring_quotation_reminds_creator_once(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $quotation = Quotation::factory()->create([
            'status' => 'sent',
            'valid_until' => now()->addDays(2)->toDateString(),
            'created_by' => $creator->id,
        ]);

        app(OperationalReminders::class)->expireQuotations();

        $this->assertNotNull($quotation->refresh()->expiry_reminder_sent_at);
        $this->assertSame(1, $creator->notifications()->count());

        app(OperationalReminders::class)->expireQuotations();
        $this->assertSame(1, $creator->notifications()->count());
    }

    public function test_payment_reminder_only_with_due_date_and_balance(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $withDue = Booking::factory()->create([
            'user_id' => $customer->id,
            'payment_due_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 5000,
        ]);
        $withoutDue = Booking::factory()->create([
            'user_id' => $customer->id,
            'payment_due_date' => null,
            'total_amount' => 5000,
        ]);

        $count = app(OperationalReminders::class)->remindPayments();

        $this->assertSame(1, $count);
        $this->assertNotNull($withDue->refresh()->last_payment_reminder_at);
        $this->assertNull($withoutDue->refresh()->last_payment_reminder_at);

        // No repeat on the same day.
        $this->assertSame(0, app(OperationalReminders::class)->remindPayments());
    }

    public function test_scheduled_campaign_dispatches_and_cancelled_does_not(): void
    {
        User::factory()->count(2)->create(['role' => 'customer']);

        $due = Campaign::factory()->create([
            'channel' => 'in_app',
            'status' => 'scheduled',
            'audience_type' => Campaign::AUDIENCE_ALL_CUSTOMERS,
            'scheduled_at' => now()->subMinute(),
        ]);
        $cancelled = Campaign::factory()->create([
            'channel' => 'in_app',
            'status' => 'cancelled',
            'audience_type' => Campaign::AUDIENCE_ALL_CUSTOMERS,
            'scheduled_at' => now()->subMinute(),
        ]);
        $future = Campaign::factory()->create([
            'channel' => 'in_app',
            'status' => 'scheduled',
            'audience_type' => Campaign::AUDIENCE_ALL_CUSTOMERS,
            'scheduled_at' => now()->addDay(),
        ]);

        $count = app(OperationalReminders::class)->dispatchDueCampaigns();

        $this->assertSame(1, $count);
        $this->assertSame('completed', $due->refresh()->status);
        $this->assertSame('cancelled', $cancelled->refresh()->status);
        $this->assertSame('scheduled', $future->refresh()->status);
    }

    public function test_travel_reminders_are_idempotent(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'travel_date' => now()->addDays(3)->toDateString(),
            'booking_status' => 'confirmed',
        ]);

        $result = app(OperationalReminders::class)->remindTravel();

        $this->assertSame(1, $result['customer']);
        $this->assertNotNull($booking->refresh()->last_travel_reminder_at);

        $again = app(OperationalReminders::class)->remindTravel();
        $this->assertSame(0, $again['customer']);
        $this->assertSame(1, $customer->notifications()->count());
    }
}
