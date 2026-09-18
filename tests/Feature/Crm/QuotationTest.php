<?php

namespace Tests\Feature\Crm;

use App\Enums\BookingSource;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuotationTest extends TestCase
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

    protected function tourPackage(): TourPackage
    {
        return TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);
    }

    protected function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'service_type' => 'tour',
            'currency' => 'INR',
            'valid_until' => now()->addDays(15)->toDateString(),
            'discount_amount' => 500,
            'items' => [
                ['item_type' => 'manual', 'description' => 'Deluxe darshan tour', 'quantity' => 2, 'unit_price' => 2500],
                ['item_type' => 'guide', 'description' => 'Hindi guide', 'quantity' => 1, 'unit_price' => 800, 'tax_amount' => 40],
            ],
        ], $overrides);
    }

    public function test_quotation_created_with_server_totals(): void
    {
        $sales = $this->staffWithRole('booking-executive');

        $response = $this->actingAs($sales)->post(route('admin.quotations.store'), $this->quotePayload());

        $quotation = Quotation::sole();
        $response->assertRedirect(route('admin.quotations.show', $quotation));

        // (2×2500) + (1×800) = 5800 subtotal; 500 header + 40 line tax.
        $this->assertSame('5800.00', (string) $quotation->subtotal);
        $this->assertSame('500.00', (string) $quotation->discount_amount);
        $this->assertSame('40.00', (string) $quotation->tax_amount);
        $this->assertSame('5340.00', (string) $quotation->total_amount);
        $this->assertMatchesRegularExpression('/^QT-\d{4}-\d{6}$/', $quotation->reference);
        $this->assertSame(1, $quotation->revision_number);
        $this->assertSame('draft', $quotation->status);
        $this->assertNotEmpty($quotation->public_token);
        $this->assertCount(2, $quotation->items);
    }

    public function test_client_totals_are_ignored(): void
    {
        $sales = $this->staffWithRole('booking-executive');

        $this->actingAs($sales)->post(route('admin.quotations.store'), $this->quotePayload([
            'subtotal' => 1, 'total_amount' => 1, 'discount_amount' => 0,
        ]));

        $quotation = Quotation::sole();
        $this->assertSame('5840.00', (string) $quotation->total_amount);
    }

    public function test_revision_freezes_prior_version(): void
    {
        $sales = $this->staffWithRole('booking-executive');
        $quotation = app(QuotationService::class)->create($this->quotePayload(), $sales);

        app(QuotationService::class)->send($quotation, $sales);

        $revision = app(QuotationService::class)->revise($quotation->refresh(), array_merge($this->quotePayload(), [
            'discount_amount' => 1000,
        ]), $sales);

        $this->assertSame($quotation->reference, $revision->reference);
        $this->assertSame(2, $revision->revision_number);
        $this->assertSame($quotation->id, $revision->root_quotation_id);
        $this->assertSame('superseded', $quotation->refresh()->status);
        $this->assertSame('draft', $revision->status);
        $this->assertSame('4840.00', (string) $revision->total_amount);

        // Old revision is frozen.
        $this->actingAs($sales)->post(route('admin.quotations.revisions.store', $quotation), $this->quotePayload())
            ->assertInvalid('quotation');
    }

    public function test_accept_reject_expiry_flow(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = app(QuotationService::class)->create($this->quotePayload(), $ops);

        // Draft cannot be accepted before sending.
        $this->actingAs($ops)->post(route('admin.quotations.accept', $quotation))->assertInvalid('status');

        $this->actingAs($ops)->post(route('admin.quotations.send', $quotation))->assertRedirect();
        $this->assertSame('sent', $quotation->refresh()->status);

        $this->actingAs($ops)->post(route('admin.quotations.accept', $quotation))->assertRedirect();
        $quotation->refresh();
        $this->assertSame('accepted', $quotation->status);
        $this->assertNotNull($quotation->accepted_at);

        // Accepted is terminal.
        $this->actingAs($ops)->post(route('admin.quotations.reject', $quotation))->assertInvalid('status');

        $other = app(QuotationService::class)->create($this->quotePayload(), $ops);
        app(QuotationService::class)->send($other, $ops);
        $this->actingAs($ops)->post(route('admin.quotations.expire', $other))->assertRedirect();
        $this->assertSame('expired', $other->refresh()->status);
    }

    public function test_expired_quotation_cannot_be_accepted(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = app(QuotationService::class)->create($this->quotePayload(['valid_until' => now()->addDay()->toDateString()]), $ops);
        app(QuotationService::class)->send($quotation, $ops);

        $this->travel(2)->days();

        $this->actingAs($ops)->post(route('admin.quotations.accept', $quotation))->assertInvalid('status');
    }

    public function test_unauthorized_staff_blocked(): void
    {
        $support = $this->staffWithRole('support-agent');

        $this->actingAs($support)->get(route('admin.quotations.index'))->assertForbidden();
        $this->actingAs($support)->post(route('admin.quotations.store'), $this->quotePayload())->assertForbidden();
    }

    public function test_send_accept_require_granular_permissions(): void
    {
        // booking-executive can create but not send/accept.
        $sales = $this->staffWithRole('booking-executive');
        $quotation = app(QuotationService::class)->create($this->quotePayload(), $sales);

        $this->actingAs($sales)->post(route('admin.quotations.send', $quotation))->assertForbidden();
        $this->actingAs($sales)->post(route('admin.quotations.accept', $quotation))->assertForbidden();
    }

    public function test_accepted_quotation_converts_to_booking_with_traceability(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = $this->tourPackage();
        $customer = User::factory()->create(['role' => 'customer']);

        $quotation = app(QuotationService::class)->create(array_merge($this->quotePayload(), [
            'customer_user_id' => $customer->id,
            'items' => [
                [
                    'item_type' => 'tour',
                    'product_id' => $package->id,
                    'description' => $package->title,
                    'quantity' => 2,
                    'unit_price' => 2500,
                    'metadata' => ['package_id' => $package->id, 'travel_date' => now()->addWeek()->toDateString(), 'adults' => 2, 'children' => 0],
                ],
            ],
        ]), $ops);

        app(QuotationService::class)->send($quotation, $ops);
        app(QuotationService::class)->accept($quotation->refresh(), $ops);

        // Live price moved (package price doubled) → quoted basis with reason.
        $package->update(['price' => 10000]);

        $response = $this->actingAs($ops)->post(route('admin.quotations.convert', $quotation), [
            'price_basis' => 'quoted',
            'price_reason' => 'Manager-approved goodwill gesture',
        ]);

        $quotation->refresh();
        $this->assertSame('converted', $quotation->status);
        $this->assertNotNull($quotation->converted_booking_id);

        $booking = $quotation->convertedBooking;
        $this->assertSame(BookingSource::Quotation->value, $booking->source->value);
        $this->assertSame($customer->id, $booking->user_id);
        $this->assertSame($quotation->id, $booking->quotation_id);
        $this->assertSame(1, $booking->quotation_revision_number);
        $this->assertEquals((float) $quotation->total_amount, (float) $booking->quoted_total_amount);
        // Honored quoted total, not the doubled live price.
        $this->assertEquals((float) $quotation->total_amount, (float) $booking->total_amount);

        $response->assertRedirect(route('admin.bookings.show', $booking));
    }

    public function test_convert_without_reason_on_drift_is_refused(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = $this->tourPackage();

        $quotation = app(QuotationService::class)->create(array_merge($this->quotePayload(), [
            'items' => [
                ['item_type' => 'tour', 'product_id' => $package->id, 'description' => $package->title, 'quantity' => 1, 'unit_price' => 5000,
                    'metadata' => ['package_id' => $package->id, 'travel_date' => now()->addWeek()->toDateString(), 'adults' => 1, 'children' => 0]],
            ],
        ]), $ops);

        app(QuotationService::class)->send($quotation, $ops);
        app(QuotationService::class)->accept($quotation->refresh(), $ops);
        $package->update(['price' => 9000]);

        $this->actingAs($ops)->post(route('admin.quotations.convert', $quotation), [
            'price_basis' => 'quoted',
        ])->assertInvalid('price_reason');
    }

    public function test_unaccepted_quotation_cannot_convert(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = app(QuotationService::class)->create($this->quotePayload(), $ops);

        $this->actingAs($ops)->post(route('admin.quotations.convert', $quotation), [])->assertInvalid('quotation');
    }

    public function test_public_token_view_needs_no_login(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = app(QuotationService::class)->create($this->quotePayload(), $ops);

        $response = $this->get(route('quotations.public', $quotation->public_token));
        $response->assertOk();

        $props = $response->viewData('page')['props']['quotation'];
        $this->assertSame($quotation->reference, $props['reference']);
        // No internal fields leak.
        $this->assertArrayNotHasKey('internal_note', $props);
        $this->assertArrayNotHasKey('public_token', $props);

        $this->get('/q/definitely-not-a-real-token')->assertNotFound();
    }

    public function test_quotation_linked_lead_timeline(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $lead = Lead::factory()->create();

        $quotation = app(QuotationService::class)->create(array_merge($this->quotePayload(), ['lead_id' => $lead->id]), $ops);
        app(QuotationService::class)->send($quotation, $ops);

        $events = $lead->refresh()->timeline()->pluck('event')->all();
        $this->assertContains('quotation_created', $events);
        $this->assertContains('quotation_sent', $events);
        $this->assertSame('quotation_sent', $lead->refresh()->status);
    }
}
