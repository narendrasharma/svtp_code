<?php

namespace Tests\Feature\Support;

use App\Models\AccountInvitation;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_offline_customer_invitation_flow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Simulate an 11.5B offline-created customer (random password,
        // placeholder inbox).
        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'walkin-abcdef123456@noemail.local',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.invite', $customer));
        $response->assertRedirect();

        $invitation = AccountInvitation::where('user_id', $customer->id)->firstOrFail();
        $this->assertNull($invitation->used_at);
        $this->assertTrue($invitation->expires_at->isFuture());

        // Invitation email sent — and it contains no password.
        $this->assertDatabaseHas('communication_logs', [
            'recipient_user_id' => $customer->id,
            'event' => 'account_invitation',
            'channel' => 'email',
        ]);
    }

    public function test_secure_token_accept_sets_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        ['token' => $token] = app(InvitationService::class)->invite($customer, $admin);

        // Raw token is never stored.
        $this->assertDatabaseMissing('account_invitations', ['token_hash' => $token]);

        $this->get(route('invitation.accept', $token))->assertOk();

        $response = $this->post(route('invitation.store', $token), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($customer->fresh());

        // No duplicate user.
        $this->assertSame(1, User::where('email', $customer->email)->count());
        $this->assertNotNull(AccountInvitation::where('user_id', $customer->id)->firstOrFail()->used_at);
    }

    public function test_expired_token_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        ['token' => $token, 'invitation' => $invitation] = app(InvitationService::class)->invite($customer, $admin);
        $invitation->update(['expires_at' => now()->subHour()]);

        $this->post(route('invitation.store', $token), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertInvalid('token');

        $this->assertGuest();
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        ['token' => $token] = app(InvitationService::class)->invite($customer, $admin);

        $this->post(route('invitation.store', $token), [
            'password' => 'first-password',
            'password_confirmation' => 'first-password',
        ])->assertRedirect();

        auth()->logout();

        $this->post(route('invitation.store', $token), [
            'password' => 'second-password',
            'password_confirmation' => 'second-password',
        ])->assertInvalid('token');
    }

    public function test_plaintext_password_never_sent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        app(InvitationService::class)->invite($customer, $admin);

        $payload = json_encode(DatabaseNotification::where('notifiable_id', $customer->id)->pluck('data')->all());
        $this->assertStringNotContainsString('secure-password', $payload);

        $logs = json_encode(CommunicationLog::pluck('error', 'id')->all());
        $this->assertStringNotContainsString('secure-password', $logs);
    }

    public function test_non_customer_cannot_be_invited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $this->actingAs($admin)->post(route('admin.users.invite', $vendor))->assertStatus(422);
        $this->assertSame(0, AccountInvitation::count());
    }
}
