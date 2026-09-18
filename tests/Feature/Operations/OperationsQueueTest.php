<?php

namespace Tests\Feature\Operations;

use App\Jobs\SendCampaign;
use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsQueueTest extends TestCase
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

    protected function draftCampaign(array $overrides = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'channel' => 'in_app',
            'status' => 'draft',
            'audience_type' => Campaign::AUDIENCE_ALL_CUSTOMERS,
        ], $overrides));
    }

    public function test_campaign_sends_via_queued_job(): void
    {
        Queue::fake();

        $admin = $this->staffWithRole('super-admin');
        User::factory()->count(3)->create(['role' => 'customer']);
        $campaign = $this->draftCampaign();

        $this->actingAs($admin)->post(route('admin.campaigns.send', $campaign))->assertRedirect();

        $this->assertSame('sending', $campaign->refresh()->status);
        $this->assertSame(3, CampaignDelivery::where('campaign_id', $campaign->id)->count());
        Queue::assertPushed(SendCampaign::class, fn (SendCampaign $job): bool => $job->campaignId === $campaign->id);
    }

    public function test_campaign_delivery_is_duplicate_safe_on_retry(): void
    {
        $admin = $this->staffWithRole('super-admin');
        $customers = User::factory()->count(2)->create(['role' => 'customer']);
        $campaign = $this->draftCampaign();

        app(CampaignService::class)->sendNow($campaign, $admin);

        // Sync queue: job ran inline, deliveries sent.
        $this->assertSame(2, CampaignDelivery::where('campaign_id', $campaign->id)->where('status', 'sent')->count());

        // Re-running the job picks up no pending rows and changes nothing.
        (new SendCampaign($campaign->id, $admin->id))->handle(app(CampaignService::class));

        $this->assertSame(2, CampaignDelivery::where('campaign_id', $campaign->id)->count());
        $this->assertSame('completed', $campaign->refresh()->status);

        foreach ($customers as $customer) {
            $this->assertSame(
                1,
                CampaignDelivery::where('campaign_id', $campaign->id)->where('user_id', $customer->id)->count()
            );
        }
    }

    public function test_failed_job_records_and_admin_ui_hides_payload(): void
    {
        $admin = $this->staffWithRole('super-admin');

        // Force a failure through the failed-jobs table directly with a
        // payload containing sensitive-looking material.
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendCampaign', 'data' => ['secret_token' => 'abc123', 'password' => 'hunter2']]),
            'exception' => "RuntimeException: boom in TestFile.php line 1\n#0 trace",
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.system.failed-jobs.index'));
        $response->assertOk();
        $response->assertDontSee('abc123', false);
        $response->assertDontSee('hunter2', false);
        $response->assertSee('SendCampaign', false);
        $response->assertSee('boom', false);
    }

    public function test_failed_job_retry_requires_manage_permission(): void
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SendCampaign']),
            'exception' => 'RuntimeException: boom',
            'failed_at' => now(),
        ]);

        // Support agents can view health but must not retry jobs.
        $agent = $this->staffWithRole('support-agent');
        $this->actingAs($agent)->get(route('admin.system.failed-jobs.index'))->assertForbidden();
        $this->actingAs($agent)->post(route('admin.system.failed-jobs.retry', $uuid))->assertForbidden();

        // Customers and vendors never reach the admin area.
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('admin.system.failed-jobs.index'))->assertForbidden();

        // Super admin may retry (job payload is gone/unknown → graceful).
        $admin = $this->staffWithRole('super-admin');
        $this->actingAs($admin)->get(route('admin.system.failed-jobs.index'))->assertOk();
    }
}
