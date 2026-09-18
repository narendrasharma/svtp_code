<?php

namespace Tests\Feature\Crm;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeadConversionTest extends TestCase
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

    public function test_existing_customer_linked_by_id(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id, 'phone' => '9810012345']);
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Existing Person', 'phone' => '9810012345']);

        $this->actingAs($sales)->post(route('admin.leads.convert', $lead), [
            'customer_user_id' => $customer->id,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame($customer->id, $lead->customer_user_id);
        $this->assertDatabaseHas('lead_timeline_entries', ['lead_id' => $lead->id, 'event' => 'converted_to_customer']);
    }

    public function test_new_lightweight_customer_created(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);

        $this->actingAs($sales)->post(route('admin.leads.convert', $lead), [
            'name' => 'Walk-in Person',
            'phone' => '9820022222',
        ])->assertRedirect();

        $customer = User::where('phone', '9820022222')->firstOrFail();
        $this->assertSame('customer', $customer->role);
        $this->assertStringEndsWith('@noemail.local', $customer->email);
        $this->assertSame('lead', $customer->source);
        $this->assertSame($customer->id, $lead->refresh()->customer_user_id);
    }

    public function test_duplicate_email_refused_with_link_hint(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);
        User::factory()->create(['role' => 'customer', 'email' => 'taken@example.com']);

        $this->actingAs($sales)->post(route('admin.leads.convert', $lead), [
            'name' => 'Someone',
            'phone' => '9830033333',
            'email' => 'taken@example.com',
        ])->assertInvalid('email');

        $this->assertNull($lead->refresh()->customer_user_id);
    }

    public function test_non_customer_account_cannot_be_linked(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $this->actingAs($sales)->post(route('admin.leads.convert', $lead), [
            'customer_user_id' => $vendor->id,
        ])->assertInvalid('customer_user_id');
    }

    public function test_convert_requires_permission(): void
    {
        // support-agent has leads.view but no leads.convert.
        $support = $this->staffWithRole('support-agent');
        $lead = Lead::factory()->create();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($support)->post(route('admin.leads.convert', $lead), [
            'customer_user_id' => $customer->id,
        ])->assertForbidden();
    }

    public function test_lead_history_retained_after_conversion(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);
        $customer = User::factory()->create(['role' => 'customer']);

        $before = $lead->timeline()->count();

        $this->actingAs($sales)->post(route('admin.leads.convert', $lead), [
            'customer_user_id' => $customer->id,
        ]);

        $this->assertSame($before + 1, $lead->refresh()->timeline()->count());
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }
}
