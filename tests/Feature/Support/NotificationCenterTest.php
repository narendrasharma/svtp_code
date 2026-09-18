<?php

namespace Tests\Feature\Support;

use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\BookingActivity;
use App\Notifications\CrmNotification;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
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

    public function test_new_customer_registration_notifies_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->post(route('register'), [
            'name' => 'Brand New',
            'email' => 'brandnew@example.com',
            'phone' => '9811111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);

        $row = DatabaseNotification::where('notifiable_id', $admin->id)->latest()->first();
        $this->assertSame('/admin/users/'.$row->data['meta']['user_id'], $row->data['action_url']);
    }

    public function test_quotation_accepted_notifies_admins(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $service = app(QuotationService::class);
        $quotation = $service->create([
            'service_type' => 'tour',
            'items' => [['description' => 'Tour', 'quantity' => 1, 'unit_price' => 1000]],
        ], $ops);
        $service->send($quotation, $ops);

        $this->actingAs($ops)->post(route('admin.quotations.accept', $quotation))->assertRedirect();

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $ops->id]);
    }

    public function test_support_reply_notifies_requester(): void
    {
        $agent = $this->staffWithRole('support-agent');
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = SupportTicket::factory()->create(['requester_user_id' => $customer->id, 'assigned_to' => $agent->id]);

        $this->actingAs($agent)->post(route('admin.support.replies.store', $ticket), ['body' => 'Fixed.'])->assertRedirect();

        $row = DatabaseNotification::where('notifiable_id', $customer->id)->latest()->first();
        $this->assertNotNull($row);
        $this->assertSame("/account/support/{$ticket->id}", $row->data['action_url']);
    }

    public function test_unread_count_and_mark_flows(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $customer->notify(new CrmNotification('admin_message', ['title' => 'Hi', 'body' => 'Hello there.']));
        $customer->notify(new CrmNotification('admin_message', ['title' => 'Hi 2', 'body' => 'Hello again.']));

        $this->assertSame(2, $customer->unreadNotifications()->count());

        $response = $this->actingAs($customer)->getJson(route('notifications.recent'));
        $response->assertOk()->assertJsonPath('unread', 2);
        $this->assertCount(2, $response->json('items'));
        $this->assertArrayHasKey('action_url', $response->json('items')[0]);

        $first = $customer->notifications()->latest()->first();
        $this->actingAs($customer)->patch(route('notifications.read', $first))->assertRedirect();
        $this->assertSame(1, $customer->refresh()->unreadNotifications()->count());

        $this->actingAs($customer)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $customer->refresh()->unreadNotifications()->count());
    }

    public function test_notification_urls_are_permission_safe(): void
    {
        // Action URLs are plain links; access is enforced by policies on
        // the target routes, never by the notification itself.
        $customer = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $other->id]);

        $customer->notify(new BookingActivity($booking, 'created'));

        // The link exists but the policy still refuses the other booking.
        $this->actingAs($customer)->get(route('account.bookings.show', $booking))->assertForbidden();
    }
}
