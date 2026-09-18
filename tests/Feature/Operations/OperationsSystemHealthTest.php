<?php

namespace Tests\Feature\Operations;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsSystemHealthTest extends TestCase
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

    public function test_health_page_shows_safe_values_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.system.index'));
        $response->assertOk();

        $health = $response->viewData('page')['props']['health'];
        $this->assertArrayHasKey('app', $health);
        $this->assertArrayHasKey('scheduler', $health);
        $this->assertArrayHasKey('queue', $health);
        $this->assertArrayHasKey('storage', $health);

        $dump = json_encode($response->viewData('page')['props']);
        foreach (['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'password'] as $secret) {
            // No secret keys or values may leak (case-insensitive scan).
            $this->assertStringNotContainsStringIgnoringCase($secret === 'password' ? 'mail_password' : $secret, $dump);
        }
    }

    public function test_scheduler_and_queue_statuses_are_honest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Fresh install: scheduler never ran.
        $health = $this->actingAs($admin)->get(route('admin.system.index'))->viewData('page')['props']['health'];
        $this->assertSame('not_detected', $health['scheduler']['status']);

        // Heartbeat → healthy.
        $this->artisan('ops:heartbeat')->assertSuccessful();
        $health = $this->actingAs($admin)->get(route('admin.system.index'))->viewData('page')['props']['health'];
        $this->assertSame('healthy', $health['scheduler']['status']);

        // Sync driver is reported as synchronous, never as an error.
        $this->assertSame('sync', $health['queue']['status']);
    }

    public function test_database_queue_status_and_failed_count(): void
    {
        config()->set('queue.default', 'database');

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\QueueHeartbeatJob']),
            'exception' => 'RuntimeException: worker down',
            'failed_at' => now(),
        ]);

        $health = app(SystemHealthService::class)->snapshot();

        $this->assertSame(1, $health['queue']['failed']);
        $this->assertNotNull($health['queue']['last_failure_at']);
    }

    public function test_storage_writability_reported(): void
    {
        $health = app(SystemHealthService::class)->snapshot();

        $this->assertTrue($health['storage']['storage_writable']);
        $this->assertTrue($health['storage']['cache_writable']);
    }

    public function test_unauthorized_access_blocked(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('admin.system.index'))->assertForbidden();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $this->actingAs($vendor)->get(route('admin.system.index'))->assertForbidden();

        $agent = $this->staffWithRole('support-agent');
        $this->actingAs($agent)->get(route('admin.system.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.activity-logs.index'))->assertForbidden();

        $super = $this->staffWithRole('super-admin');
        $this->actingAs($super)->get(route('admin.system.index'))->assertOk();
        $this->actingAs($super)->get(route('admin.reports.index'))->assertOk();
    }

    public function test_operations_settings_update_and_audit(): void
    {
        $super = $this->staffWithRole('super-admin');

        $this->actingAs($super)->post(route('admin.settings.operations.update'), [
            'ops_reminders_enabled' => true,
            'ops_followup_reminders_enabled' => true,
            'ops_quotation_expiry_enabled' => true,
            'ops_quotation_expiry_reminder_days' => 5,
            'ops_payment_reminder_offsets' => '7,1',
            'ops_travel_reminder_customer_offsets' => '2',
            'ops_travel_reminder_vendor_offsets' => '2',
            'ops_campaigns_scheduled_enabled' => true,
            'ops_admin_digest_frequency' => 'daily',
            'ops_notification_retention_days' => 90,
            'ops_notify_lead_created' => false,
        ])->assertRedirect();

        $this->assertSame('5', Setting::getValue('ops.quotation_expiry_reminder_days'));
        $this->assertSame('7,1', Setting::getValue('ops.payment_reminder_offsets'));
        $this->assertSame('daily', Setting::getValue('ops.admin_digest_frequency'));
        $this->assertTrue(ActivityLog::where('module', 'system')->exists());

        // Limited staff cannot change operations settings.
        $agent = $this->staffWithRole('support-agent');
        $this->actingAs($agent)->post(route('admin.settings.operations.update'), [])->assertForbidden();
    }
}
