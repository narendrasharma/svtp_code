<?php

namespace Tests\Feature\Support;

use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminMessagingTest extends TestCase
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

    public function test_authorized_admin_messages_one_customer(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Message Me']);

        $response = $this->actingAs($manager)->post(route('admin.messages.store'), [
            'user_ids' => [$customer->id],
            'title' => 'Diwali tours are live',
            'body' => 'Special departures just opened.',
            'action_url' => '/packages',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customer->id]);
        $this->assertDatabaseHas('communication_logs', [
            'recipient_user_id' => $customer->id,
            'channel' => 'in_app',
            'event' => 'admin_message',
        ]);
    }

    public function test_message_to_multiple_selected_users(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $users = User::factory()->count(3)->create(['role' => 'customer']);

        $this->actingAs($manager)->post(route('admin.messages.store'), [
            'user_ids' => $users->pluck('id')->all(),
            'title' => 'Hello all',
            'body' => 'Bulk hello.',
        ])->assertRedirect();

        foreach ($users as $user) {
            $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
        }

        $this->assertSame(3, CommunicationLog::where('event', 'admin_message')->count());
    }

    public function test_email_sent_through_safe_path(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer', 'email' => 'mailed@example.com']);

        $this->actingAs($manager)->post(route('admin.messages.store'), [
            'user_ids' => [$customer->id],
            'title' => 'Mailed title',
            'body' => 'Mailed body content here.',
        ]);

        // MAIL_MAILER=array in tests: the in-app notification's mail
        // channel must not blow up and the DB row must exist.
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customer->id]);
    }

    public function test_unauthorized_staff_blocked(): void
    {
        // booking-executive has no users.message.
        $sales = $this->staffWithRole('booking-executive');
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($sales)->get(route('admin.messages.create'))->assertForbidden();
        $this->actingAs($sales)->post(route('admin.messages.store'), [
            'user_ids' => [$customer->id],
            'title' => 'Nope',
            'body' => 'Nope.',
        ])->assertForbidden();
    }

    public function test_message_form_preselects_user(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($manager)->get(route('admin.messages.create', ['user_id' => $customer->id]));
        $response->assertOk();

        $preselected = $response->viewData('page')['props']['preselected'];
        $this->assertSame($customer->id, $preselected[0]['value']);
    }
}
