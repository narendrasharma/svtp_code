<?php

namespace Tests\Feature\Hotel;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use App\Enums\HotelReservationStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\HotelBooking;
use App\Models\HotelBookingItem;
use App\Models\HotelReservationNight;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelBookingService;
use App\Services\HotelOperationsService;
use App\Support\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HotelOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        app(ModuleManager::class)->setEnabled('hotels', true);
    }

    public function test_operations_routes_are_module_gated(): void
    {
        app(ModuleManager::class)->setEnabled('hotels', false);
        $this->actingAs($this->vendor())->get(route('vendor.hotel.operations', absolute: false))->assertNotFound();
        $this->actingAs($this->admin())->get(route('admin.hotel.operations', absolute: false))->assertNotFound();
    }

    public function test_vendor_operations_are_owned_and_foreign_selector_is_blocked(): void
    {
        $vendor = $this->vendor();
        $foreign = $this->vendor();
        $own = $this->property($vendor->vendorProfile);
        $other = $this->property($foreign->vendorProfile);

        $this->assertDatabaseHas('properties', ['id' => $own->id, 'vendor_profile_id' => $vendor->vendorProfile->id]);

        $room = $this->room($own, 1);
        $ownBooking = $this->booking($own, $room, now()->toDateString(), now()->addDay()->toDateString(), HotelBookingStatus::Confirmed);
        $foreignBooking = $this->booking($other, $this->room($other, 1), now()->toDateString(), now()->addDay()->toDateString(), HotelBookingStatus::Confirmed);
        $data = app(HotelOperationsService::class)->dashboard(collect([$own]), now()->toDateString());
        $this->assertContains($ownBooking->booking_number, array_column($data['arrivals'], 'booking_number'));
        $this->assertNotContains($foreignBooking->booking_number, array_column($data['arrivals'], 'booking_number'));
    }

    public function test_dashboard_classifies_operational_lists_and_attention(): void
    {
        $property = $this->property(null, ['timezone' => 'Pacific/Auckland']);
        $today = app(HotelOperationsService::class)->propertyToday($property);
        $room = $this->room($property, 2);
        $arrival = $this->booking($property, $room, $today, Carbon::parse($today)->addDay()->toDateString(), HotelBookingStatus::Confirmed, ['special_requests' => 'Late arrival']);
        $this->booking($property, $room, Carbon::parse($today)->subDay()->toDateString(), $today, HotelBookingStatus::CheckedIn);
        $inHouse = $this->booking($property, $room, Carbon::parse($today)->subDay()->toDateString(), Carbon::parse($today)->addDay()->toDateString(), HotelBookingStatus::CheckedIn);
        $departure = $this->booking($property, $room, Carbon::parse($today)->subDay()->toDateString(), $today, HotelBookingStatus::CheckedIn);
        $overdue = $this->booking($property, $room, Carbon::parse($today)->subDay()->toDateString(), Carbon::parse($today)->addDay()->toDateString(), HotelBookingStatus::Confirmed);

        $data = app(HotelOperationsService::class)->dashboard(collect([$property]), $today);
        $this->assertContains($arrival->booking_number, array_column($data['arrivals'], 'booking_number'));
        $this->assertContains($inHouse->booking_number, array_column($data['in_house'], 'booking_number'));
        $this->assertContains($overdue->booking_number, array_column($data['attention'], 'booking_number'));
        $this->assertSame(1, collect($data['departures'])->where('booking_number', $departure->booking_number)->count());
        $this->assertSame([], collect($data['in_house'])->where('booking_number', $overdue->booking_number)->all());
    }

    public function test_cancelled_and_no_show_bookings_are_excluded(): void
    {
        $property = $this->property();
        $room = $this->room($property, 4);
        $date = now()->toDateString();
        $cancelled = $this->booking($property, $room, $date, now()->addDay()->toDateString(), HotelBookingStatus::Cancelled);
        $noShow = $this->booking($property, $room, $date, now()->addDay()->toDateString(), HotelBookingStatus::NoShow);
        $data = app(HotelOperationsService::class)->dashboard(collect([$property]), $date);
        $numbers = array_column($data['arrivals'], 'booking_number');
        $this->assertNotContains($cancelled->booking_number, $numbers);
        $this->assertNotContains($noShow->booking_number, $numbers);
    }

    public function test_room_summary_uses_reservations_overrides_blocks_stop_sell_and_zero_is_safe(): void
    {
        $property = $this->property();
        $room = $this->room($property, 2);
        $date = now()->toDateString();
        $booking = $this->booking($property, $room, $date, now()->addDay()->toDateString(), HotelBookingStatus::Confirmed);
        $booking->items->first()->reservationNights()->create(['room_type_id' => $room->id, 'stay_date' => $date, 'quantity' => 1, 'status' => HotelReservationStatus::Confirmed]);
        HotelRoomInventory::create(['hotel_room_type_id' => $room->id, 'inventory_date' => $date, 'capacity_override' => 2, 'blocked_units' => 0, 'stop_sell' => false]);
        $data = app(HotelOperationsService::class)->dashboard(collect([$property]), $date);
        $this->assertSame(2, $data['rooms'][0]['capacity']);
        $this->assertSame(1, $data['rooms'][0]['reserved']);
        $this->assertSame(1, $data['rooms'][0]['available']);
        $this->assertGreaterThanOrEqual(0, $data['kpis']['occupancy']);
    }

    public function test_vendor_status_action_uses_central_transition_and_preserves_payment_snapshot(): void
    {
        $vendor = $this->vendor();
        $property = $this->property($vendor->vendorProfile);
        $room = $this->room($property, 2);
        $booking = $this->booking($property, $room, now()->toDateString(), now()->addDay()->toDateString(), HotelBookingStatus::Confirmed);
        $total = $booking->total;
        $snapshot = $booking->pricing_snapshot;
        app(HotelBookingService::class)->changeStatus($booking, HotelBookingStatus::CheckedIn, $vendor);
        $booking->refresh();
        $this->assertSame(HotelBookingStatus::CheckedIn, $booking->status);
        $this->assertSame(HotelPaymentStatus::Unpaid, $booking->payment_status);
        $this->assertEquals($total, $booking->total);
        $this->assertSame($snapshot, $booking->pricing_snapshot);
    }

    public function test_no_show_releases_consumption_without_changing_payment(): void
    {
        $property = $this->property();
        $room = $this->room($property, 2);
        $booking = $this->booking($property, $room, now()->toDateString(), now()->addDay()->toDateString(), HotelBookingStatus::Confirmed);
        $booking->items->first()->reservationNights()->create(['room_type_id' => $room->id, 'stay_date' => now()->toDateString(), 'quantity' => 1, 'status' => HotelReservationStatus::Confirmed]);
        app(HotelBookingService::class)->changeStatus($booking, HotelBookingStatus::NoShow, $this->admin());
        $this->assertSame(HotelBookingStatus::NoShow, $booking->fresh()->status);
        $this->assertSame(HotelPaymentStatus::Unpaid, $booking->fresh()->payment_status);
        $this->assertSame(0, HotelReservationNight::where('hotel_booking_item_id', $booking->items()->value('id'))->whereIn('status', HotelReservationNight::consumingStatuses())->count());
    }

    public function test_dashboard_payload_excludes_pricing_snapshot_and_inventory_notes(): void
    {
        $property = $this->property();
        $room = $this->room($property, 1);
        $date = now()->toDateString();
        HotelRoomInventory::create(['hotel_room_type_id' => $room->id, 'inventory_date' => $date, 'note' => 'Internal note']);
        $this->booking($property, $room, $date, now()->addDay()->toDateString(), HotelBookingStatus::Confirmed);
        $data = app(HotelOperationsService::class)->dashboard(collect([$property]), $date);
        $this->assertArrayNotHasKey('pricing_snapshot', $data['arrivals'][0]);
        $this->assertArrayNotHasKey('note', $data['rooms'][0]);
    }

    public function test_selected_property_uses_its_timezone_for_operational_today(): void
    {
        $property = $this->property(null, ['timezone' => 'Pacific/Auckland']);
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:30:00', 'UTC'));
        $expected = Carbon::now('Pacific/Auckland')->toDateString();

        $this->assertSame($expected, app(HotelOperationsService::class)->propertyToday($property));
        Carbon::setTestNow();
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');

        return $admin->fresh();
    }

    protected function vendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        return $vendor->fresh();
    }

    protected function property(?VendorProfile $vendor = null, array $overrides = []): Property
    {
        $property = Property::factory()->create(array_merge(['status' => PropertyStatus::Published->value, 'published_at' => now()], $overrides));
        if ($vendor) {
            $property->forceFill(['vendor_profile_id' => $vendor->id])->save();
        }

        return $property->fresh();
    }

    protected function room(Property $property, int $capacity): HotelRoomType
    {
        $room = HotelRoomType::factory()->create(['status' => RoomTypeStatus::Active->value, 'total_units' => $capacity]);
        $room->forceFill(['property_id' => $property->id])->save();

        return $room->fresh();
    }

    protected function booking(Property $property, HotelRoomType $room, string $checkIn, string $checkOut, HotelBookingStatus $status, array $overrides = []): HotelBooking
    {
        $booking = HotelBooking::factory()->create(array_merge(['status' => $status->value, 'check_in' => $checkIn, 'check_out' => $checkOut, 'pricing_snapshot' => ['total' => '220.00', 'internal' => 'hidden']], $overrides));
        $booking->forceFill(['property_id' => $property->id, 'vendor_profile_id' => $property->vendor_profile_id])->save();
        HotelBookingItem::factory()->create(['hotel_booking_id' => $booking->id, 'room_type_id' => $room->id, 'check_in' => $checkIn, 'check_out' => $checkOut, 'status' => $status->value]);

        return $booking->load('items');
    }
}
