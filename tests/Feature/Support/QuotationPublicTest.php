<?php

namespace Tests\Feature\Support;

use App\Models\Quotation;
use App\Models\User;
use App\Services\Comms\ShareService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuotationPublicTest extends TestCase
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

    protected function sentQuotation(User $actor): Quotation
    {
        $service = app(QuotationService::class);
        $quotation = $service->create([
            'service_type' => 'tour',
            'valid_until' => now()->addDays(10)->toDateString(),
            'items' => [['description' => 'Tour', 'quantity' => 1, 'unit_price' => 1000]],
        ], $actor);
        $service->send($quotation, $actor);

        return $quotation->refresh();
    }

    public function test_valid_signed_quote_accessible_without_login(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = $this->sentQuotation($ops);

        $response = $this->get(route('quotations.public', $quotation->public_token));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('decisionUrls', $props);
        $this->assertTrue($props['canDecide']);
        $this->assertArrayNotHasKey('internal_note', $props['quotation']);
    }

    public function test_invalid_signature_blocked(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = $this->sentQuotation($ops);

        // Unsigned mutation attempts are rejected even with a valid token.
        $this->post(route('quotations.public.accept', $quotation->public_token), [], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertSame('sent', $quotation->refresh()->status);
    }

    public function test_accept_via_signed_url_records_source_and_notifies(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $customer = User::factory()->create(['role' => 'customer']);
        $quotation = $this->sentQuotation($ops);
        $quotation->update(['customer_user_id' => $customer->id]);

        $urls = app(ShareService::class)->quotationDecisionUrls($quotation);

        $this->post($urls['accept_url'], ['customer_note' => 'Morning pickup please.'])->assertRedirect();

        $quotation->refresh();
        $this->assertSame('accepted', $quotation->status);
        $this->assertNotNull($quotation->accepted_at);
        $this->assertSame('Morning pickup please.', $quotation->customer_note);

        // Staff notified (AdminAlert fan-out).
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $ops->id]);
        // No booking is auto-created.
        $this->assertNull($quotation->converted_booking_id);
    }

    public function test_expired_quote_cannot_accept(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $service = app(QuotationService::class);
        $quotation = $service->create([
            'service_type' => 'tour',
            'valid_until' => now()->addDay()->toDateString(),
            'items' => [['description' => 'Tour', 'quantity' => 1, 'unit_price' => 1000]],
        ], $ops);
        $service->send($quotation, $ops);

        $this->travel(3)->days();

        $urls = app(ShareService::class)->quotationDecisionUrls($quotation->refresh());

        $this->post($urls['accept_url'])->assertInvalid('status');
        $this->assertSame('sent', $quotation->refresh()->status);
    }

    public function test_superseded_revision_cannot_accept(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $service = app(QuotationService::class);
        $quotation = $service->create([
            'service_type' => 'tour',
            'valid_until' => now()->addDays(10)->toDateString(),
            'items' => [['description' => 'Tour', 'quantity' => 1, 'unit_price' => 1000]],
        ], $ops);
        $service->send($quotation, $ops);
        $service->revise($quotation->refresh(), [
            'items' => [['description' => 'Tour v2', 'quantity' => 1, 'unit_price' => 1200]],
        ], $ops);

        $old = $quotation->refresh();
        $this->assertSame('superseded', $old->status);

        $urls = app(ShareService::class)->quotationDecisionUrls($old);

        $this->post($urls['accept_url'])->assertInvalid('quotation');
        $this->assertSame('superseded', $old->refresh()->status);
    }

    public function test_accepted_quote_is_idempotent(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = $this->sentQuotation($ops);
        $urls = app(ShareService::class)->quotationDecisionUrls($quotation);

        $this->post($urls['accept_url'])->assertRedirect();
        $this->assertSame('accepted', $quotation->refresh()->status);

        $this->post($urls['accept_url'])->assertInvalid('status');
        $this->assertSame('accepted', $quotation->refresh()->status);
    }

    public function test_reject_works(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = $this->sentQuotation($ops);
        $urls = app(ShareService::class)->quotationDecisionUrls($quotation);

        $this->post($urls['reject_url'])->assertRedirect();

        $quotation->refresh();
        $this->assertSame('rejected', $quotation->status);
        $this->assertNotNull($quotation->rejected_at);
    }

    public function test_internal_notes_absent_from_public_view(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = $this->sentQuotation($ops);
        $quotation->update(['internal_note' => 'Secret margin talk', 'terms' => 'Pay 50% advance']);

        $response = $this->get(route('quotations.public', $quotation->public_token));
        $payload = json_encode($response->viewData('page')['props']);

        $this->assertStringNotContainsString('Secret margin talk', $payload);
        $this->assertStringContainsString('Pay 50% advance', $payload);
    }
}
