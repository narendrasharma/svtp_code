<?php

namespace Tests\Feature\Support;

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\BookingActivity;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CampaignTest extends TestCase
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

    public function test_create_draft_with_reference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.campaigns.store'), [
            'name' => 'Diwali departures',
            'subject' => 'Diwali tours are live',
            'content' => 'Special departures just opened.',
            'channel' => 'email',
            'audience_type' => 'all_customers',
        ]);

        $campaign = Campaign::sole();
        $response->assertRedirect(route('admin.campaigns.show', $campaign));
        $this->assertMatchesRegularExpression('/^CMP-\d{6}$/', $campaign->reference);
        $this->assertSame('draft', $campaign->status);
    }

    public function test_selected_user_audience(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $picked = User::factory()->count(2)->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin)->post(route('admin.campaigns.store'), [
            'name' => 'Picked',
            'subject' => 'Hi picked',
            'content' => 'Only for you two.',
            'channel' => 'in_app',
            'audience_type' => 'selected',
            'audience_filter' => ['user_ids' => $picked->pluck('id')->all()],
        ])->assertRedirect();

        $campaign = Campaign::sole();
        $this->assertSame(2, app(CampaignService::class)->audienceCount($campaign));

        $this->actingAs($admin)->post(route('admin.campaigns.send', $campaign))->assertRedirect();

        $this->assertSame(2, CampaignDelivery::where('campaign_id', $campaign->id)->count());
        $this->assertSame('completed', $campaign->refresh()->status);
        foreach ($picked as $user) {
            $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
        }
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $other->id]);
    }

    public function test_customer_and_vendor_audiences(): void
    {
        $service = app(CampaignService::class);

        User::factory()->count(2)->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);
        User::factory()->create(['role' => 'admin']);

        $customers = Campaign::factory()->create(['audience_type' => 'all_customers']);
        $vendors = Campaign::factory()->create(['audience_type' => 'all_vendors']);
        $all = Campaign::factory()->create(['audience_type' => 'all_users']);

        $this->assertSame(2, $service->audienceCount($customers));
        $this->assertSame(1, $service->audienceCount($vendors));
        // all_users = customers + vendors, never staff.
        $this->assertSame(3, $service->audienceCount($all));
    }

    public function test_marketing_opt_out_respected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $optedOut = User::factory()->create(['role' => 'customer', 'marketing_email_opt_in' => false]);
        $optedIn = User::factory()->create(['role' => 'customer', 'marketing_email_opt_in' => true]);

        $this->actingAs($admin)->post(route('admin.campaigns.store'), [
            'name' => 'Opt test',
            'subject' => 'Hi',
            'content' => 'Hello.',
            'channel' => 'email',
            'audience_type' => 'all_customers',
        ]);

        $campaign = Campaign::sole();
        $this->actingAs($admin)->post(route('admin.campaigns.send', $campaign))->assertRedirect();

        $this->assertDatabaseHas('campaign_deliveries', [
            'campaign_id' => $campaign->id,
            'user_id' => $optedOut->id,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseHas('campaign_deliveries', [
            'campaign_id' => $campaign->id,
            'user_id' => $optedIn->id,
            'status' => 'sent',
        ]);
    }

    public function test_duplicate_delivery_prevented(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin)->post(route('admin.campaigns.store'), [
            'name' => 'Once',
            'subject' => 'Hi once',
            'content' => 'Hello once.',
            'channel' => 'in_app',
            'audience_type' => 'all_customers',
        ]);

        $campaign = Campaign::sole();
        $this->actingAs($admin)->post(route('admin.campaigns.send', $campaign))->assertRedirect();

        $this->assertSame(1, CampaignDelivery::where('campaign_id', $campaign->id)->count());

        // Second send attempt is refused — terminal state, rows intact.
        $this->actingAs($admin)->post(route('admin.campaigns.send', $campaign))->assertInvalid('campaign');
        $this->assertSame(1, CampaignDelivery::where('campaign_id', $campaign->id)->count());
    }

    public function test_unauthorized_staff_blocked(): void
    {
        // booking-executive has no campaign permissions.
        $sales = $this->staffWithRole('booking-executive');

        $this->actingAs($sales)->get(route('admin.campaigns.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('admin.campaigns.store'), [
            'name' => 'Nope',
            'subject' => 'Nope',
            'content' => 'Nope.',
            'channel' => 'email',
            'audience_type' => 'all_customers',
        ])->assertForbidden();
    }

    public function test_transactional_notification_unaffected_by_opt_out(): void
    {
        // Opted-out customers still receive booking mail + DB rows.
        $customer = User::factory()->create(['role' => 'customer', 'marketing_email_opt_in' => false]);
        $booking = Booking::factory()->create(['user_id' => $customer->id]);

        $customer->notify(new BookingActivity($booking, 'created'));

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customer->id]);
    }
}
