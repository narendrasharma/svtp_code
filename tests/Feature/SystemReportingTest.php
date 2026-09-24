<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\TaxiBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SystemReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_dashboard_and_cross_module_report_keep_booking_values_by_currency(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        HotelBooking::factory()->create(['currency' => 'USD', 'total' => '220.00']);
        TaxiBooking::factory()->create(['currency' => 'INR', 'total_amount' => '2500.00']);

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $system = $dashboard->viewData('page')['props']['analytics']['system'];

        $this->assertSame(1, $system['modules']['hotels']['bookings']);
        $this->assertSame(1, $system['modules']['taxi']['bookings']);
        $this->assertSame('220.00', $system['modules']['hotels']['value_by_currency']['USD']['amount']);
        $this->assertSame('2500.00', $system['modules']['taxi']['value_by_currency']['INR']['amount']);

        $report = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'bookings']))->assertOk();
        $rows = $report->viewData('page')['props']['rows'];

        $this->assertCount(2, $rows);
        $this->assertSame(['Hotels', 'Taxi'], collect($rows)->pluck('module')->sort()->values()->all());
        $this->assertArrayHasKey('pagination', $report->viewData('page')['props']);

        $filtered = $this->actingAs($admin)->get(route('admin.reports.index', ['tab' => 'bookings', 'module' => 'taxi']))->viewData('page')['props'];
        $this->assertCount(1, $filtered['rows']);
        $this->assertSame('Taxi', $filtered['rows'][0]['module']);
    }
}
