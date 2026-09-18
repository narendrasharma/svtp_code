<?php

namespace Tests\Feature\Platform;

use App\Models\Booking;
use App\Models\NumberSeries;
use App\Models\User;
use App\Services\BookingReference;
use App\Services\NumberSeriesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NumberSeriesTest extends TestCase
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

    public function test_booking_reference_comes_from_number_series(): void
    {
        $reference = BookingReference::generate();

        $this->assertMatchesRegularExpression('/^BK-\d{4}-\d{6}$/', $reference);
        $this->assertSame(2, (int) NumberSeries::where('entity', 'booking')->firstOrFail()->next_number);
    }

    public function test_sequential_generation_is_unique(): void
    {
        $references = [];

        for ($i = 0; $i < 20; $i++) {
            $references[] = app(NumberSeriesService::class)->next('booking');
        }

        $this->assertSame(20, count(array_unique($references)));

        $sequences = array_map(fn (string $ref): int => (int) substr($ref, -6), $references);
        $sorted = $sequences;
        sort($sorted);
        $this->assertSame($sorted, $sequences);
        $this->assertSame(range($sequences[0], $sequences[0] + 19), $sorted);
    }

    public function test_padding_is_applied(): void
    {
        $series = NumberSeries::where('entity', 'customer')->firstOrFail();

        $this->assertSame('CUS-000001', app(NumberSeriesService::class)->next('customer'));
        $this->assertSame('CUS-000002', app(NumberSeriesService::class)->preview($series->fresh()));
    }

    public function test_yearly_reset_starts_new_sequence(): void
    {
        $service = app(NumberSeriesService::class);

        Carbon::setTestNow(Carbon::create(2026, 5, 10));
        $first = $service->next('invoice');

        Carbon::setTestNow(Carbon::create(2027, 1, 2));
        $second = $service->next('invoice');

        Carbon::setTestNow();

        $this->assertSame('INV-2026-000001', $first);
        $this->assertSame('INV-2027-000001', $second);
    }

    public function test_prefix_change_affects_future_only(): void
    {
        $before = Booking::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.number-series.update', 'booking'), [
            'prefix' => 'XX',
            'separator' => '-',
            'include_year' => true,
            'include_month' => false,
            'padding' => 6,
            'start_number' => 1,
            'next_number' => $before->id + 100,
            'reset_cycle' => 'yearly',
        ])->assertRedirect();

        $after = Booking::factory()->create();

        $this->assertStringStartsWith('BK-', $before->refresh()->booking_reference_id);
        $this->assertStringStartsWith('XX-', $after->booking_reference_id);
    }

    public function test_historical_references_are_never_renamed(): void
    {
        $legacy = Booking::factory()->create();
        $legacy->forceFill(['booking_reference_id' => 'BK-2026-ABC123'])->save();

        app(NumberSeriesService::class)->next('booking');
        app(NumberSeriesService::class)->next('booking');

        $this->assertSame('BK-2026-ABC123', $legacy->refresh()->booking_reference_id);
    }

    public function test_invalid_configuration_is_rejected(): void
    {
        $service = app(NumberSeriesService::class);

        foreach ([
            ['prefix' => 'bk!', 'padding' => 6],
            ['prefix' => 'TOOLONGPREFIX1', 'padding' => 6],
            ['prefix' => 'BK', 'padding' => 2],
            ['prefix' => 'BK', 'padding' => 6, 'reset_cycle' => 'fortnightly'],
            ['prefix' => 'BK', 'padding' => 6, 'separator' => ';'],
        ] as $input) {
            try {
                $service->validateConfiguration($input);
                $this->fail('Invalid configuration was accepted: '.json_encode($input));
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_counter_cannot_be_lowered_through_admin(): void
    {
        app(NumberSeriesService::class)->next('booking');
        app(NumberSeriesService::class)->next('booking');

        $response = $this->actingAs($this->admin())->put(route('admin.number-series.update', 'booking'), [
            'prefix' => 'BK',
            'separator' => '-',
            'include_year' => true,
            'include_month' => false,
            'padding' => 6,
            'start_number' => 1,
            'next_number' => 1,
            'reset_cycle' => 'yearly',
        ]);

        $response->assertInvalid('next_number');
        $this->assertSame(3, (int) NumberSeries::where('entity', 'booking')->firstOrFail()->next_number);
    }

    public function test_future_entity_definitions_exist_without_tables(): void
    {
        // Phase 11.5B promoted lead/quotation/payment to real domains,
        // 11.5C promoted support tickets and campaigns; the remaining
        // future entities stay definitions-only.
        foreach (['lead', 'quotation', 'payment', 'support_ticket', 'campaign', 'taxi_ride', 'hotel_reservation'] as $entity) {
            $this->assertDatabaseHas('number_series', ['entity' => $entity]);
        }

        $this->assertTrue(Schema::hasTable('leads'));
        $this->assertTrue(Schema::hasTable('quotations'));
        $this->assertTrue(Schema::hasTable('booking_payments'));
        $this->assertTrue(Schema::hasTable('support_tickets'));
        $this->assertTrue(Schema::hasTable('campaigns'));
    }

    public function test_number_series_page_requires_settings_permission(): void
    {
        $this->actingAs($this->admin())->get(route('admin.number-series.index'))->assertOk();

        $staff = User::factory()->create(['role' => 'admin']);
        $staff->assignRole('support-agent');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staff->fresh())->get(route('admin.number-series.index'))->assertForbidden();
    }
}
