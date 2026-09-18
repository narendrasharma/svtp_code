<?php

namespace Tests\Feature\Support;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupportAuthorizationTest extends TestCase
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

    public function test_customer_cannot_view_another_ticket(): void
    {
        $mine = User::factory()->create(['role' => 'customer']);
        $theirs = User::factory()->create(['role' => 'customer']);
        $ticket = SupportTicket::factory()->create(['requester_user_id' => $theirs->id]);

        $this->actingAs($mine)->get(route('account.support.show', $ticket))->assertNotFound();
        $this->actingAs($mine)->post(route('account.support.replies.store', $ticket), ['body' => 'Hi'])->assertNotFound();
    }

    public function test_vendor_isolation(): void
    {
        $vendorA = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendorA->id, 'is_active' => true]);
        $vendorB = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendorB->id, 'is_active' => true]);

        $ticket = SupportTicket::factory()->create(['requester_user_id' => $vendorA->id]);

        $this->actingAs($vendorB)->get(route('vendor.support.show', $ticket))->assertNotFound();

        // Vendors cannot reach the admin desk at all.
        $this->actingAs($vendorA)->get(route('admin.support.index'))->assertForbidden();
    }

    public function test_support_agent_sees_only_assigned_tickets(): void
    {
        $agent = $this->staffWithRole('support-agent');
        $other = $this->staffWithRole('support-agent');

        $mine = SupportTicket::factory()->create(['assigned_to' => $agent->id]);
        $theirs = SupportTicket::factory()->create(['assigned_to' => $other->id]);
        $unassigned = SupportTicket::factory()->create(['assigned_to' => null]);

        $response = $this->actingAs($agent)->get(route('admin.support.index'));
        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['tickets']['data'])->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);

        $this->actingAs($agent)->get(route('admin.support.show', $theirs))->assertNotFound();
        $this->actingAs($agent)->get(route('admin.support.show', $mine))->assertOk();
    }

    public function test_manager_with_view_all_sees_everything(): void
    {
        $manager = $this->staffWithRole('operations-manager');

        SupportTicket::factory()->create(['assigned_to' => null]);
        SupportTicket::factory()->create();

        $response = $this->actingAs($manager)->get(route('admin.support.index'));
        $this->assertCount(2, $response->viewData('page')['props']['tickets']['data']);
    }

    public function test_granular_staff_permissions_enforced(): void
    {
        // support-agent has view+reply but not assign/close.
        $agent = $this->staffWithRole('support-agent');
        $ticket = SupportTicket::factory()->create(['assigned_to' => $agent->id]);

        $this->actingAs($agent)->post(route('admin.support.assign', $ticket), ['assigned_to' => null])->assertForbidden();
        $this->actingAs($agent)->patch(route('admin.support.status', $ticket), ['status' => 'closed'])->assertForbidden();
        $this->actingAs($agent)->post(route('admin.support.replies.store', $ticket), ['body' => 'On it.'])->assertRedirect();
    }

    public function test_content_manager_has_no_desk_access(): void
    {
        $content = $this->staffWithRole('content-manager');

        $this->actingAs($content)->get(route('admin.support.index'))->assertForbidden();
        $this->actingAs($content)->get(route('admin.campaigns.index'))->assertForbidden();
        $this->actingAs($content)->get(route('admin.communication-logs.index'))->assertForbidden();
    }
}
