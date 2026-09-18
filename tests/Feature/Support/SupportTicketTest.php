<?php

namespace Tests\Feature\Support;

use App\Models\Booking;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Storage::fake('support');
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

    protected function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    public function test_customer_creates_ticket(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $category = SupportCategory::where('slug', 'booking')->firstOrFail();

        $response = $this->actingAs($customer)->post(route('account.support.store'), [
            'subject' => 'Change my travel date',
            'body' => 'Need to move from Monday to Friday please.',
            'category_id' => $category->id,
            'priority' => 'high',
        ]);

        $ticket = SupportTicket::sole();
        $response->assertRedirect(route('account.support.show', $ticket));

        $this->assertMatchesRegularExpression('/^SUP-\d{6}$/', $ticket->reference);
        $this->assertSame('open', $ticket->status);
        $this->assertSame($customer->id, $ticket->requester_user_id);
        $this->assertSame($category->id, $ticket->category_id);
        $this->assertNotNull($ticket->last_reply_at);
        // Admins are alerted.
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);
    }

    public function test_vendor_creates_own_ticket_with_vendor_context(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        $this->actingAs($vendor)->post(route('vendor.support.store'), [
            'subject' => 'KYC stuck in review',
            'body' => 'My documents have been pending for a week.',
            'category_id' => SupportCategory::where('slug', 'vendor-kyc')->firstOrFail()->id,
        ])->assertRedirect();

        $ticket = SupportTicket::sole();
        $this->assertSame($profile->id, $ticket->vendor_profile_id);
    }

    public function test_staff_reply_and_requester_reply_flip_status(): void
    {
        $agent = $this->staffWithRole('support-agent');
        $customer = $this->customer();
        $ticket = SupportTicket::factory()->create(['requester_user_id' => $customer->id, 'assigned_to' => $agent->id]);

        $this->actingAs($agent)->post(route('admin.support.replies.store', $ticket), [
            'body' => 'We moved you to Friday, confirmation follows.',
        ])->assertRedirect();

        $this->assertSame('pending_customer', $ticket->refresh()->status);

        $this->actingAs($customer)->post(route('account.support.replies.store', $ticket), [
            'body' => 'Thank you!',
        ])->assertRedirect();

        $this->assertSame('pending_staff', $ticket->refresh()->status);
    }

    public function test_internal_note_hidden_from_requester(): void
    {
        $agent = $this->staffWithRole('support-agent');
        $customer = $this->customer();
        $ticket = SupportTicket::factory()->create(['requester_user_id' => $customer->id, 'assigned_to' => $agent->id]);

        $this->actingAs($agent)->post(route('admin.support.notes.store', $ticket), [
            'body' => 'Customer is a VIP, escalate refunds.',
        ])->assertRedirect();

        $response = $this->actingAs($customer)->get(route('account.support.show', $ticket));
        $response->assertOk();

        $messages = $response->viewData('page')['props']['ticket']['messages'];
        $bodies = collect($messages)->pluck('body')->all();

        $this->assertNotContains('Customer is a VIP, escalate refunds.', $bodies);
        // Staff desk still shows it, flagged.
        $staffView = $this->actingAs($agent)->get(route('admin.support.show', $ticket));
        $staffBodies = collect($staffView->viewData('page')['props']['ticket']['messages'])->pluck('body')->all();
        $this->assertContains('Customer is a VIP, escalate refunds.', $staffBodies);
    }

    public function test_attachment_upload_and_authorization(): void
    {
        $agent = $this->staffWithRole('support-agent');
        $customer = $this->customer();
        $ticket = SupportTicket::factory()->create(['requester_user_id' => $customer->id, 'assigned_to' => $agent->id]);

        $file = UploadedFile::fake()->create('id-proof.pdf', 200, 'application/pdf');

        $this->actingAs($customer)->post(route('account.support.replies.store', $ticket), [
            'body' => 'Attaching my ID.',
            'attachments' => [$file],
        ])->assertRedirect();

        $attachment = $ticket->messages()->where('body', 'Attaching my ID.')->firstOrFail()->attachments()->sole();
        $this->assertSame('id-proof.pdf', $attachment->original_name);
        Storage::disk('support')->assertExists($attachment->path);

        // Owner can download.
        $this->actingAs($customer)
            ->get(route('account.support.attachments.download', [$ticket, $attachment]))
            ->assertOk();

        // Strangers cannot.
        $this->actingAs($this->customer())
            ->get(route('account.support.attachments.download', [$ticket, $attachment]))
            ->assertNotFound();

        // Staff can.
        $this->actingAs($agent)
            ->get(route('admin.support.attachments.download', [$ticket, $attachment]))
            ->assertOk();
    }

    public function test_unsafe_attachment_rejected(): void
    {
        $customer = $this->customer();
        $ticket = SupportTicket::factory()->create(['requester_user_id' => $customer->id]);

        $evil = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');

        $this->actingAs($customer)->post(route('account.support.replies.store', $ticket), [
            'body' => 'Try this.',
            'attachments' => [$evil],
        ])->assertInvalid('attachments');

        $this->assertSame(0, $ticket->messages()->first()->attachments()->count());
    }

    public function test_status_workflow_and_assignment(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $agent = $this->staffWithRole('support-agent');
        $ticket = SupportTicket::factory()->create(['assigned_to' => null]);

        $this->actingAs($manager)->post(route('admin.support.assign', $ticket), ['assigned_to' => $agent->id])->assertRedirect();
        $this->assertSame($agent->id, $ticket->refresh()->assigned_to);

        $this->actingAs($manager)->patch(route('admin.support.status', $ticket), ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('resolved', $ticket->refresh()->status);

        // Terminal tickets refuse replies until reopened.
        $customer = $ticket->requester;
        $this->actingAs($customer)->post(route('account.support.replies.store', $ticket), ['body' => 'Wait, one more thing'])
            ->assertInvalid('ticket');

        $this->actingAs($manager)->patch(route('admin.support.status', $ticket), ['status' => 'open'])->assertRedirect();
        $this->assertSame('open', $ticket->refresh()->status);
        $this->assertNull($ticket->refresh()->closed_at);
    }

    public function test_linked_booking_context_authorized(): void
    {
        $manager = $this->staffWithRole('operations-manager');
        $customer = $this->customer();
        $booking = Booking::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($customer)->post(route('account.support.store'), [
            'subject' => 'About my booking',
            'body' => 'Question.',
            'booking_id' => $booking->id,
        ])->assertRedirect();

        $ticket = SupportTicket::sole();
        $this->assertSame($booking->id, $ticket->booking_id);

        $response = $this->actingAs($manager)->get(route('admin.support.show', $ticket));
        $bookingCtx = $response->viewData('page')['props']['ticket']['booking'];
        $this->assertSame($booking->booking_reference_id, $bookingCtx['booking_reference_id']);

        // Someone else's booking id is silently dropped, not linked.
        $stranger = $this->customer();
        $this->actingAs($stranger)->post(route('account.support.store'), [
            'subject' => 'Sneaky link',
            'body' => 'Trying.',
            'booking_id' => $booking->id,
        ]);

        $this->assertNull(SupportTicket::where('subject', 'Sneaky link')->firstOrFail()->booking_id);
    }

    public function test_ticket_reference_uses_number_series(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('account.support.store'), [
            'subject' => 'First',
            'body' => 'One.',
        ]);
        $this->actingAs($customer)->post(route('account.support.store'), [
            'subject' => 'Second',
            'body' => 'Two.',
        ]);

        $refs = SupportTicket::orderBy('id')->pluck('reference')->all();
        $this->assertNotSame($refs[0], $refs[1]);

        foreach ($refs as $ref) {
            $this->assertMatchesRegularExpression('/^SUP-\d{6}$/', $ref);
        }
    }
}
