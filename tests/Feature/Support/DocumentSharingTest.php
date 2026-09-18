<?php

namespace Tests\Feature\Support;

use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\Quotation;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\Comms\ProviderNotConfiguredException;
use App\Services\Comms\ShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentSharingTest extends TestCase
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

    public function test_quotation_email_uses_secure_link_and_logs(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = Quotation::factory()->create();

        $response = $this->actingAs($ops)->postJson(route('admin.quotations.share', $quotation), [
            'channel' => 'email',
            'to_email' => 'guest@example.com',
        ]);

        $response->assertOk()->assertJsonPath('sent', true);
        $this->assertDatabaseHas('communication_logs', [
            'channel' => 'email',
            'template_key' => 'quotation_sent',
            'event' => 'document_shared',
        ]);

        $log = CommunicationLog::latest()->first();
        // Masked, not raw.
        $this->assertStringNotContainsString('guest@example.com', (string) $log->destination_masked);
    }

    public function test_invoice_share_authorized_and_signed(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = Booking::factory()->create();

        $response = $this->actingAs($ops)->postJson(route('admin.bookings.share-invoice', $booking), [
            'channel' => 'email',
            'to_email' => 'guest@example.com',
        ]);

        $response->assertOk();
        $url = $response->json('secure_url');
        $this->assertStringContainsString('signature=', $url);

        // Signed link works without login and returns the PDF.
        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_receipt_share_authorized(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);
        $booking = Booking::factory()->create(['package_id' => $package->id]);
        $payment = app(BookingPaymentService::class)->recordPayment($booking, 1000, PaymentMethod::Cash, $ops);

        $response = $this->actingAs($ops)->postJson(route('admin.bookings.share-receipt', [$booking, $payment]), [
            'channel' => 'email',
            'to_email' => 'guest@example.com',
        ]);

        $response->assertOk();
        $url = $response->json('secure_url');

        auth()->logout();
        $get = $this->get($url)->assertOk();
        $this->assertStringContainsString($payment->reference, $get->getContent());
    }

    public function test_whatsapp_manual_share_url_generated(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = Quotation::factory()->create();

        $response = $this->actingAs($ops)->postJson(route('admin.quotations.share', $quotation), [
            'channel' => 'whatsapp',
            'to_phone' => '9876543210',
        ]);

        $response->assertOk();
        $url = $response->json('whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/919876543210?text=', $url);
        $this->assertStringContainsString(urlencode($quotation->reference), $url);

        $this->assertDatabaseHas('communication_logs', [
            'channel' => 'manual_share',
            'provider' => 'whatsapp-manual',
        ]);
    }

    public function test_no_sensitive_data_in_share_text(): void
    {
        $share = app(ShareService::class);

        $message = $share->renderShareMessage('quotation_sent', 'whatsapp', [
            'customer_name' => 'Asha',
            'quotation_reference' => 'QT-2026-000001',
            'amount' => '5000',
            'secure_url' => 'http://localhost/q/abc',
            'site_name' => 'Test',
        ]);

        $this->assertStringNotContainsStringIgnoringCase('commission', (string) $message);
        $this->assertStringNotContainsStringIgnoringCase('internal', (string) $message);
        $this->assertStringContainsString('QT-2026-000001', (string) $message);
    }

    public function test_sms_unavailable_when_provider_absent(): void
    {
        $share = app(ShareService::class);

        $this->assertFalse($share->smsProvider()->isConfigured());

        $this->expectException(ProviderNotConfiguredException::class);
        $share->smsProvider()->send('919876543210', 'Hello');
    }

    public function test_share_requires_permission(): void
    {
        // booking-executive has no communications.send.
        $sales = $this->staffWithRole('booking-executive');
        $quotation = Quotation::factory()->create();

        $this->actingAs($sales)->postJson(route('admin.quotations.share', $quotation), [
            'channel' => 'email',
            'to_email' => 'guest@example.com',
        ])->assertForbidden();
    }

    public function test_communication_log_created_for_shares(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $booking = Booking::factory()->create();

        $this->actingAs($ops)->postJson(route('admin.bookings.share-invoice', $booking), [
            'channel' => 'whatsapp',
            'to_phone' => '+91 98765 43210',
        ])->assertOk();

        $log = CommunicationLog::latest()->first();
        $this->assertSame('manual_share', $log->channel);
        // Masked phone, full number nowhere in the row.
        $this->assertStringNotContainsString('9876543210', json_encode($log->toArray()));
    }
}
