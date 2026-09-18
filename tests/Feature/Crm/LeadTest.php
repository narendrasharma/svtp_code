<?php

namespace Tests\Feature\Crm;

use App\Models\Enquiry;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function staffWithRole(string $role): User
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff->fresh();
    }

    protected function leadPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Traveller',
            'phone' => '9876543210',
            'email' => 'traveller@example.com',
            'service_type' => 'tour',
            'priority' => 'high',
        ], $overrides);
    }

    public function test_staff_with_permission_can_create_lead(): void
    {
        $sales = $this->staffWithRole('booking-executive');

        $response = $this->actingAs($sales)->post(route('admin.leads.store'), $this->leadPayload());

        $response->assertRedirect();
        $lead = Lead::sole();
        $this->assertMatchesRegularExpression('/^LEAD-\d{6}$/', $lead->reference);
        $this->assertSame('new', $lead->status);
        $this->assertSame($sales->id, $lead->created_by);
        $this->assertDatabaseHas('lead_timeline_entries', ['lead_id' => $lead->id, 'event' => 'created']);
    }

    public function test_lead_reference_comes_from_number_series(): void
    {
        $first = app(LeadService::class)->createLead($this->leadPayload(), $this->admin());
        $second = app(LeadService::class)->createLead($this->leadPayload(['phone' => '9999999999']), $this->admin());

        $this->assertNotSame($first->reference, $second->reference);
        $this->assertMatchesRegularExpression('/^LEAD-\d{6}$/', $second->reference);
    }

    public function test_customer_and_vendor_cannot_access_crm(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        $this->actingAs($customer)->get(route('admin.leads.index'))->assertForbidden();
        $this->actingAs($vendor)->get(route('admin.leads.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.leads.store'), $this->leadPayload())->assertForbidden();
    }

    public function test_sales_staff_sees_only_assigned_leads(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $other = $this->staffWithRole('booking-executive');

        $mine = Lead::factory()->create(['assigned_to' => $sales->id, 'name' => 'Mine Lead']);
        $theirs = Lead::factory()->create(['assigned_to' => $other->id, 'name' => 'Theirs Lead']);
        $unassigned = Lead::factory()->create(['assigned_to' => null, 'name' => 'Nobody Lead']);

        $response = $this->actingAs($sales)->get(route('admin.leads.index'));
        $response->assertOk();

        $names = collect($response->viewData('page')['props']['leads']['data'])->pluck('name')->all();
        $this->assertContains('Mine Lead', $names);
        $this->assertNotContains('Theirs Lead', $names);
        $this->assertNotContains('Nobody Lead', $names);

        // Direct access to another lead 404s (no existence leak).
        $this->actingAs($sales)->get(route('admin.leads.show', $theirs))->assertNotFound();
        $this->actingAs($sales)->get(route('admin.leads.show', $mine))->assertOk();
        $this->actingAs($sales)->get(route('admin.leads.show', $unassigned))->assertNotFound();
    }

    public function test_manager_with_view_all_sees_everything(): void
    {
        $manager = $this->staffWithRole('operations-manager');

        Lead::factory()->create(['assigned_to' => null, 'name' => 'Nobody Lead']);
        Lead::factory()->create(['name' => 'Some Lead']);

        $response = $this->actingAs($manager)->get(route('admin.leads.index'));
        $names = collect($response->viewData('page')['props']['leads']['data'])->pluck('name')->all();

        $this->assertContains('Nobody Lead', $names);
        $this->assertContains('Some Lead', $names);
    }

    public function test_assignment_requires_permission_and_notifies(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => null]);

        // Sales (no leads.assign) is blocked.
        $this->actingAs($sales)->post(route('admin.leads.assign', $lead), ['assigned_to' => $sales->id])->assertForbidden();

        $this->actingAs($manager)->post(route('admin.leads.assign', $lead), ['assigned_to' => $sales->id])->assertRedirect();

        $this->assertSame($sales->id, $lead->refresh()->assigned_to);
        $this->assertDatabaseHas('lead_timeline_entries', ['lead_id' => $lead->id, 'event' => 'assigned']);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $sales->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_assignment_rejects_non_staff(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer']);
        $lead = Lead::factory()->create();

        $this->actingAs($manager)
            ->post(route('admin.leads.assign', $lead), ['assigned_to' => $customer->id])
            ->assertInvalid('assigned_to');
    }

    public function test_status_transitions_and_lost_reason(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id, 'status' => 'new']);

        $this->actingAs($sales)->patch(route('admin.leads.status', $lead), ['status' => 'contacted'])->assertRedirect();
        $this->assertSame('contacted', $lead->refresh()->status);

        // Lost without a reason is refused.
        $this->actingAs($sales)->patch(route('admin.leads.status', $lead), ['status' => 'lost'])->assertInvalid('lost_reason');

        $this->actingAs($sales)->patch(route('admin.leads.status', $lead), ['status' => 'lost', 'lost_reason' => 'Booked elsewhere'])->assertRedirect();
        $lead->refresh();
        $this->assertSame('lost', $lead->status);
        $this->assertSame('Booked elsewhere', $lead->lost_reason);

        // Terminal states are immutable.
        $this->actingAs($sales)->patch(route('admin.leads.status', $lead), ['status' => 'contacted'])->assertInvalid('status');
    }

    public function test_lead_sources_crud_and_delete_guard(): void
    {
        $manager = $this->staffWithRole('operations-manager');

        $this->actingAs($manager)->post(route('admin.lead-sources.store'), ['name' => 'Roadshow'])->assertRedirect();
        $source = LeadSource::where('slug', 'roadshow')->firstOrFail();

        Lead::factory()->create(['source_id' => $source->id]);

        // Used source cannot be deleted.
        $this->actingAs($manager)->delete(route('admin.lead-sources.destroy', $source))->assertInvalid('source');
        $this->assertDatabaseHas('lead_sources', ['id' => $source->id]);

        // But it can be deactivated.
        $this->actingAs($manager)->put(route('admin.lead-sources.update', $source), ['name' => 'Roadshow', 'is_active' => false])->assertRedirect();
        $this->assertFalse($source->refresh()->is_active);
    }

    public function test_website_enquiry_auto_creates_lead_and_form_still_works(): void
    {
        // Quick enquiry (no CAPTCHA) through the real public endpoint.
        $response = $this->post(route('enquiries.store'), [
            'enquiry_type' => 'quick',
            'full_name' => 'Enquiry Person',
            'phone' => '9811111111',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enquiries', ['full_name' => 'Enquiry Person']);

        $enquiry = Enquiry::where('full_name', 'Enquiry Person')->firstOrFail();
        $lead = Lead::where('enquiry_id', $enquiry->id)->first();

        $this->assertNotNull($lead);
        $this->assertSame('Enquiry Person', $lead->name);
        $this->assertSame('9811111111', $lead->phone);
        $this->assertSame('tour', $lead->service_type);
        $this->assertSame('website', $lead->source?->slug);
        $this->assertMatchesRegularExpression('/^LEAD-\d{6}$/', $lead->reference);
    }

    public function test_tour_plan_enquiry_maps_travel_details(): void
    {
        $enquiry = Enquiry::factory()->create([
            'enquiry_type' => 'tour_plan',
            'full_name' => 'Plan Person',
            'adults' => 3,
            'arrival_date' => now()->addMonth()->toDateString(),
            'departure_date' => now()->addMonth()->addDays(3)->toDateString(),
        ]);

        $lead = Lead::where('enquiry_id', $enquiry->id)->firstOrFail();

        $this->assertSame(3, $lead->adults);
        $this->assertNotNull($lead->travel_start_date);
        $this->assertNotNull($lead->travel_end_date);
    }
}
