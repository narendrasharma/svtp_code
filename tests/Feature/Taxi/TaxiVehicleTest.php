<?php

namespace Tests\Feature\Taxi;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiVehicleTest extends TestCase
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

    public function test_admin_can_create_vehicle_for_vendor(): void
    {
        $vendorProfile = VendorProfile::factory()->create();
        $type = VehicleType::factory()->create();

        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.vehicles.store', absolute: false), [
            'vendor_profile_id' => $vendorProfile->id,
            'vehicle_type_id' => $type->id,
            'name' => 'Blue Innova',
            'registration_number' => 'UP80AB1234',
            'passenger_capacity' => 6,
            'luggage_capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'vendor_profile_id' => $vendorProfile->id,
            'vehicle_type_id' => $type->id,
            'name' => 'Blue Innova',
            'registration_number' => 'UP80AB1234',
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    public function test_registration_number_unique_per_vendor(): void
    {
        $vendorProfile = VendorProfile::factory()->create();
        Vehicle::factory()->create([
            'vendor_profile_id' => $vendorProfile->id,
            'registration_number' => 'UP80AB1234',
        ]);

        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.vehicles.store', absolute: false), [
            'vendor_profile_id' => $vendorProfile->id,
            'vehicle_type_id' => null,
            'name' => 'Duplicate',
            'registration_number' => 'UP80AB1234',
            'passenger_capacity' => 4,
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors('registration_number');
    }

    public function test_vendor_cannot_access_other_vendor_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $otherVendor = $this->makeVendor();

        $this->actingAs($otherVendor)->get(route('vendor.taxi.vehicles.show', parameters: $vehicle, absolute: false))->assertNotFound();
    }

    public function test_vendor_can_upload_vehicle_document(): void
    {
        Storage::fake('vendor_kyc');

        $vendor = $this->makeVendor();
        $vehicle = Vehicle::factory()->create(['vendor_profile_id' => $vendor->vendorProfile->id]);

        $response = $this->actingAs($vendor)->post(route('vendor.taxi.vehicles.documents.store', parameters: $vehicle, absolute: false), [
            'document_type' => 'insurance',
            'document_file' => UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf'),
            'document_number' => '1234567890',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->id,
            'document_type' => 'insurance',
            'status' => 'pending',
        ]);
    }
}
