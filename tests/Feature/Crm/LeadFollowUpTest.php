<?php

namespace Tests\Feature\Crm;

use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeadFollowUpTest extends TestCase
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

    public function test_follow_up_create_and_complete(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);

        $due = now()->addDay()->format('Y-m-d\TH:i');
        $response = $this->actingAs($sales)->post(route('admin.leads.follow-ups.store', $lead), [
            'due_at' => $due,
            'type' => 'call',
            'note' => 'Call about Diwali dates',
        ]);

        $response->assertRedirect();
        $followUp = LeadFollowUp::sole();
        $this->assertSame('pending', $followUp->status);
        $this->assertNotNull($lead->refresh()->next_follow_up_at);
        $this->assertDatabaseHas('lead_timeline_entries', ['lead_id' => $lead->id, 'event' => 'follow_up_added']);

        $this->actingAs($sales)->patch(route('admin.follow-ups.complete', $followUp))->assertRedirect();

        $followUp->refresh();
        $this->assertSame('completed', $followUp->status);
        $this->assertNotNull($followUp->completed_at);
        $this->assertNotNull($lead->refresh()->last_contacted_at);
        $this->assertNull($lead->refresh()->next_follow_up_at);
        $this->assertDatabaseHas('lead_timeline_entries', ['lead_id' => $lead->id, 'event' => 'follow_up_completed']);
    }

    public function test_follow_up_cancel(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);
        $followUp = LeadFollowUp::factory()->create(['lead_id' => $lead->id]);

        $this->actingAs($sales)->patch(route('admin.follow-ups.cancel', $followUp))->assertRedirect();
        $this->assertSame('cancelled', $followUp->refresh()->status);
    }

    public function test_overdue_and_due_today_queues(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $lead = Lead::factory()->create();

        $overdue = LeadFollowUp::factory()->create(['lead_id' => $lead->id, 'due_at' => now()->subDay()]);
        $today = LeadFollowUp::factory()->create(['lead_id' => $lead->id, 'due_at' => now()->addHour()]);
        LeadFollowUp::factory()->create(['lead_id' => $lead->id, 'due_at' => now()->addWeek()]);

        $this->assertTrue($overdue->refresh()->isOverdue());
        $this->assertFalse($today->refresh()->isOverdue());

        $response = $this->actingAs($manager)->get(route('admin.follow-ups.index', ['filter' => 'overdue']));
        $ids = collect($response->viewData('page')['props']['followUps']['data'])->pluck('id')->all();
        $this->assertContains($overdue->id, $ids);
        $this->assertNotContains($today->id, $ids);

        $response = $this->actingAs($manager)->get(route('admin.follow-ups.index', ['filter' => 'due_today']));
        $ids = collect($response->viewData('page')['props']['followUps']['data'])->pluck('id')->all();
        $this->assertContains($today->id, $ids);
    }

    public function test_follow_up_on_invisible_lead_is_hidden(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $other = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $other->id]);
        $followUp = LeadFollowUp::factory()->create(['lead_id' => $lead->id]);

        $this->actingAs($sales)->patch(route('admin.follow-ups.complete', $followUp))->assertNotFound();
        $this->assertSame('pending', $followUp->refresh()->status);
    }

    public function test_follow_up_validation(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);

        $this->actingAs($sales)->post(route('admin.leads.follow-ups.store', $lead), [
            'due_at' => '',
            'type' => 'pigeon',
        ])->assertInvalid(['due_at', 'type']);
    }
}
