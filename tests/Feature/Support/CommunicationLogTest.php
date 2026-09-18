<?php

namespace Tests\Feature\Support;

use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommunicationLogTest extends TestCase
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

    public function test_log_page_lists_entries_with_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        CommunicationLog::create([
            'recipient_user_id' => $customer->id,
            'recipient_type' => 'customer',
            'channel' => 'email',
            'template_key' => 'quotation_sent',
            'event' => 'document_shared',
            'destination_masked' => CommunicationLog::maskEmail($customer->email),
            'status' => 'sent',
            'sent_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.communication-logs.index'));
        $response->assertOk();
        $this->assertCount(1, $response->viewData('page')['props']['logs']['data']);

        $filtered = $this->actingAs($admin)->get(route('admin.communication-logs.index', ['channel' => 'sms']));
        $this->assertCount(0, $filtered->viewData('page')['props']['logs']['data']);
    }

    public function test_masking_helpers(): void
    {
        $this->assertSame('j***@example.com', CommunicationLog::maskEmail('john@example.com'));
        $this->assertSame('***3210', CommunicationLog::maskPhone('+91 98765 43210'));
        $this->assertNull(CommunicationLog::maskEmail(null));
        $this->assertNull(CommunicationLog::maskEmail('not-an-email'));
    }

    public function test_log_requires_permission(): void
    {
        $sales = $this->staffWithRole('booking-executive');

        $this->actingAs($sales)->get(route('admin.communication-logs.index'))->assertForbidden();
    }

    public function test_direct_message_creates_log_row(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($manager)->post(route('admin.messages.store'), [
            'user_ids' => [$customer->id],
            'title' => 'Logged title',
            'body' => 'Logged body.',
        ]);

        $log = CommunicationLog::where('event', 'admin_message')->firstOrFail();
        $this->assertSame('in_app', $log->channel);
        $this->assertSame('sent', $log->status);
        $this->assertSame($manager->id, $log->created_by);
        $this->assertSame('Logged title', $log->metadata['title']);
    }
}
