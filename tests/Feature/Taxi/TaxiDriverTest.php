<?php

namespace Tests\Feature\Taxi;

use App\Models\Driver;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin->fresh();
    }

    protected function makeVendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        return $vendor;
    }

    public function test_vendor_can_create_driver(): void
    {
        $vendor = $this->makeVendor();

        $response = $this->actingAs($vendor)->post(route('vendor.taxi.drivers.store', absolute: false), [
            'first_name' => 'Rakesh',
            'last_name' => 'Kumar',
            'phone' => '9999999999',
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('drivers', [
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'first_name' => 'Rakesh',
            'phone' => '9999999999',
        ]);
    }

    public function test_vendor_cannot_view_other_vendor_driver(): void
    {
        $driver = Driver::factory()->create();
        $otherVendor = $this->makeVendor();

        $this->actingAs($otherVendor)->get(route('vendor.taxi.drivers.show', parameters: $driver, absolute: false))->assertNotFound();
    }

    public function test_admin_can_update_driver_details(): void
    {
        $driver = Driver::factory()->create(['employment_status' => 'active']);

        $response = $this->actingAs($this->makeAdmin())->put(route('admin.taxi.drivers.update', parameters: $driver, absolute: false), [
            'vendor_profile_id' => $driver->vendor_profile_id,
            'first_name' => 'Updated',
            'phone' => '8888888888',
            'availability_status' => 'break',
            'employment_status' => 'suspended',
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $driver->refresh();

        $this->assertSame('Updated', $driver->first_name);
        $this->assertSame('8888888888', $driver->phone);
        $this->assertSame('break', $driver->availability_status);
        $this->assertSame('suspended', $driver->employment_status);
        $this->assertFalse((bool) $driver->is_active);
    }

    public function test_vendor_can_add_availability_window(): void
    {
        $vendor = $this->makeVendor();
        $driver = Driver::factory()->create(['vendor_profile_id' => $vendor->vendorProfile->id]);

        $response = $this->actingAs($vendor)->post(route('vendor.taxi.drivers.availability.store', parameters: $driver, absolute: false), [
            'date' => now()->addDay()->toDateString(),
            'status' => 'on_leave',
            'reason' => 'Family event',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('driver_availabilities', [
            'driver_id' => $driver->id,
            'status' => 'on_leave',
            'reason' => 'Family event',
        ]);
    }

    public function test_vendor_can_upload_driver_document(): void
    {
        Storage::fake('vendor_kyc');

        $vendor = $this->makeVendor();
        $driver = Driver::factory()->create(['vendor_profile_id' => $vendor->vendorProfile->id]);

        $response = $this->actingAs($vendor)->post(route('vendor.taxi.drivers.documents.store', parameters: $driver, absolute: false), [
            'document_type' => 'driving_license',
            'document_file' => UploadedFile::fake()->create('license.pdf', 120, 'application/pdf'),
            'document_number' => 'DL-123456',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('driver_documents', [
            'driver_id' => $driver->id,
            'document_type' => 'driving_license',
            'status' => 'pending',
        ]);
    }
}
