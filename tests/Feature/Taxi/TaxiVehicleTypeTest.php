<?php

namespace Tests\Feature\Taxi;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiVehicleTypeTest extends TestCase
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

    public function test_admin_can_create_vehicle_type(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(route('admin.taxi.vehicle-types.store', absolute: false), [
            'name' => 'Premium Sedan',
            'slug' => 'premium-sedan',
            'description' => 'Comfortable sedan for city rides',
            'passenger_capacity' => 4,
            'luggage_capacity' => 3,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vehicle_types', [
            'name' => 'Premium Sedan',
            'slug' => 'premium-sedan',
            'passenger_capacity' => 4,
            'luggage_capacity' => 3,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_vehicle_type(): void
    {
        $type = VehicleType::factory()->create(['name' => 'SUV', 'luggage_capacity' => 3]);

        $response = $this->actingAs($this->makeAdmin())->put(route('admin.taxi.vehicle-types.update', parameters: $type, absolute: false), [
            'name' => 'SUV Deluxe',
            'description' => 'Spacious SUV',
            'passenger_capacity' => 6,
            'luggage_capacity' => 5,
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $response->assertRedirect();

        $type->refresh();
        $this->assertSame('SUV Deluxe', $type->name);
        $this->assertSame(6, $type->passenger_capacity);
        $this->assertSame(5, $type->luggage_capacity);
        $this->assertFalse((bool) $type->is_active);
        $this->assertSame(5, $type->sort_order);
    }

    public function test_admin_can_replace_and_remove_vehicle_type_image(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdmin();
        $type = VehicleType::factory()->create();

        $this->actingAs($admin)->post(route('admin.taxi.vehicle-types.update', parameters: $type, absolute: false), [
            '_method' => 'put',
            'name' => $type->name,
            'description' => $type->description,
            'passenger_capacity' => $type->passenger_capacity,
            'luggage_capacity' => $type->luggage_capacity,
            'is_active' => true,
            'sort_order' => $type->sort_order,
            'image_upload' => UploadedFile::fake()->image('sedan.jpg'),
        ])->assertRedirect();

        $type->refresh();
        $oldImage = $type->image_path;
        Storage::disk('public')->assertExists($oldImage);

        $this->actingAs($admin)->post(route('admin.taxi.vehicle-types.update', parameters: $type, absolute: false), [
            '_method' => 'put',
            'name' => $type->name,
            'description' => $type->description,
            'passenger_capacity' => $type->passenger_capacity,
            'luggage_capacity' => $type->luggage_capacity,
            'is_active' => true,
            'sort_order' => $type->sort_order,
            'image_upload' => UploadedFile::fake()->image('suv.jpg'),
        ])->assertRedirect();

        $type->refresh();
        $newImage = $type->image_path;
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($newImage);

        $this->actingAs($admin)->post(route('admin.taxi.vehicle-types.update', parameters: $type, absolute: false), [
            '_method' => 'put',
            'name' => $type->name,
            'description' => $type->description,
            'passenger_capacity' => $type->passenger_capacity,
            'luggage_capacity' => $type->luggage_capacity,
            'is_active' => true,
            'sort_order' => $type->sort_order,
            'remove_image' => true,
        ])->assertRedirect();

        $this->assertNull($type->refresh()->image_path);
        Storage::disk('public')->assertMissing($newImage);
    }

    public function test_vehicle_type_cannot_be_deleted_when_in_use(): void
    {
        $type = VehicleType::factory()->create();
        Vehicle::factory()->create(['vehicle_type_id' => $type->id]);

        $response = $this->actingAs($this->makeAdmin())->delete(route('admin.taxi.vehicle-types.destroy', parameters: $type, absolute: false));

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('vehicle_types', ['id' => $type->id]);
    }
}
