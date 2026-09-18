<?php

namespace Tests\Feature\Crm;

use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrmSearchTest extends TestCase
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

    protected function groupKeys(array $groups): array
    {
        return collect($groups)->pluck('key')->all();
    }

    public function test_lead_global_search(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $lead = Lead::factory()->create(['name' => 'Searchable Lead', 'phone' => '9810098100']);

        $response = $this->actingAs($ops)->getJson(route('admin.search', ['q' => 'Searchable Lead']));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'leads');
        $this->assertNotNull($group);
        $this->assertContains($lead->reference, collect($group['items'])->pluck('label')->all());
    }

    public function test_quotation_global_search(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $quotation = Quotation::factory()->create();

        $response = $this->actingAs($ops)->getJson(route('admin.search', ['q' => substr($quotation->reference, 0, 12)]));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'quotations');
        $this->assertNotNull($group);
        $this->assertContains($quotation->reference, collect($group['items'])->pluck('label')->all());
    }

    public function test_payment_reference_search(): void
    {
        $ops = $this->staffWithRole('operations-manager');
        $package = TourPackage::factory()->create(['price' => 5000, 'discounted_price' => null, 'is_active' => true]);
        $booking = Booking::factory()->create(['package_id' => $package->id]);
        $payment = app(BookingPaymentService::class)->recordPayment($booking, 1000, PaymentMethod::Cash, $ops);

        $response = $this->actingAs($ops)->getJson(route('admin.search', ['q' => substr($payment->reference, 0, 12)]));
        $response->assertOk();

        $group = collect($response->json('groups'))->firstWhere('key', 'payments');
        $this->assertNotNull($group);
        $this->assertContains($payment->reference, collect($group['items'])->pluck('label')->all());
    }

    public function test_search_permission_filtering(): void
    {
        // Custom role: leads + bookings, but no quotations.view.
        $role = Role::create(['name' => 'lead-hunter', 'guard_name' => 'web']);
        $role->givePermissionTo(['dashboard.view', 'leads.view', 'bookings.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $hunter = User::factory()->create(['role' => 'admin']);
        $hunter->assignRole('lead-hunter');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $hunter = $hunter->fresh();

        Lead::factory()->create(['assigned_to' => $hunter->id, 'name' => 'Scoped Search Lead']);
        $quotation = Quotation::factory()->create();

        $response = $this->actingAs($hunter)->getJson(route('admin.search', ['q' => 'Scoped Search Lead']));
        $keys = $this->groupKeys($response->json('groups'));
        $this->assertContains('leads', $keys);
        $this->assertNotContains('quotations', $keys);

        // Quotation reference invisible without the permission.
        $response = $this->actingAs($hunter)->getJson(route('admin.search', ['q' => substr($quotation->reference, 0, 12)]));
        $this->assertNotContains('quotations', $this->groupKeys($response->json('groups')));

        // Assigned-only scoping applies to search too.
        $other = $this->staffWithRole('booking-executive');
        Lead::factory()->create(['assigned_to' => $other->id, 'name' => 'Hidden Other Lead']);
        $response = $this->actingAs($hunter)->getJson(route('admin.search', ['q' => 'Hidden Other Lead']));
        $group = collect($response->json('groups'))->firstWhere('key', 'leads');
        $this->assertNull($group);
    }

    public function test_sidebar_entries_are_permission_aware(): void
    {
        $sales = $this->staffWithRole('booking-executive');

        $response = $this->actingAs($sales)->get(route('admin.leads.index'));
        $response->assertOk();

        $nav = $response->viewData('page')['props']['adminNavigation'];
        $keys = collect($nav)->pluck('key')->all();

        $this->assertContains('crm', $keys);

        $crm = collect($nav)->firstWhere('key', 'crm');
        $labels = collect($crm['items'])->pluck('label')->all();
        $this->assertContains('Leads', $labels);
        $this->assertContains('Quotations', $labels);
    }
}
