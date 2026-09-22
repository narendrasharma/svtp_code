<?php

namespace Database\Seeders;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\HotelAmenity;
use App\Models\HotelBedType;
use App\Models\HotelChargeRule;
use App\Models\HotelCustomFieldDefinition;
use App\Models\HotelDailyRate;
use App\Models\HotelRatePlan;
use App\Models\HotelRateSeason;
use App\Models\HotelRoomInventory;
use App\Models\HotelRoomType;
use App\Models\HotelRoomUnit;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelCustomFieldService;
use App\Services\HotelRoomService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CodeCanyon-safe Hotel demo data (12B.1).
 *
 * Explicitly invoked only — NEVER called from DatabaseSeeder:
 *
 *   php artisan db:seed --class=HotelDemoSeeder
 *
 * Idempotent: fixed slugs + firstOrCreate throughout; re-running never
 * duplicates or touches real data. Generic locations, example.com
 * identities, no external images, no real branding.
 */
class HotelDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $vendor = $this->vendor();

            $amenities = HotelAmenity::whereIn('slug', ['free-wi-fi', 'parking', 'swimming-pool', 'restaurant'])
                ->pluck('id')
                ->all();

            $harbour = $this->property(
                'demo-harbour-hotel',
                'Demo Harbour Hotel',
                'hotel',
                $vendor->id,
                PropertyStatus::Published->value,
                ['star_rating' => 4, 'is_featured' => true, 'country_code' => 'US'],
                $amenities
            );

            $lakeside = $this->property(
                'demo-lakeside-homestay',
                'Demo Lakeside Homestay',
                'homestay',
                $vendor->id,
                PropertyStatus::Published->value,
                ['country_code' => 'US'],
                $amenities
            );

            $this->property(
                'demo-downtown-hostel',
                'Demo Downtown Hostel',
                'hostel',
                null,
                PropertyStatus::Draft->value,
                ['country_code' => 'US'],
                []
            );

            $this->rooms($harbour, $lakeside);
            $this->customFields($harbour, $lakeside);
            $this->inventory($harbour, $lakeside);
            $this->pricing($harbour, $lakeside);
        });

        $this->command->info('Hotel demo data ready (3 demo properties).');
    }

    protected function vendor(): VendorProfile
    {
        $user = User::firstOrCreate(
            ['email' => 'demo-stays@example.com'],
            ['name' => 'Demo Stays Owner', 'password' => Hash::make('password'), 'role' => 'vendor']
        );

        return VendorProfile::firstOrCreate(
            ['slug' => 'demo-stays'],
            [
                'user_id' => $user->id,
                'business_name' => 'Demo Stays',
                'entity_type' => 'company',
                'email' => 'demo-stays@example.com',
                'city' => 'Demo City',
                'is_active' => true,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, int>  $amenityIds
     */
    protected function property(string $slug, string $name, string $typeSlug, ?int $vendorId, string $status, array $overrides, array $amenityIds): Property
    {
        $type = PropertyType::where('slug', $typeSlug)->firstOrFail();

        $property = Property::firstOrCreate(
            ['slug' => $slug],
            [
                'property_type_id' => $type->id,
                'name' => $name,
                'short_description' => 'A generic demo property for exploring the Hotels module.',
                'description' => '<p>Demo description with comfortable rooms and friendly service.</p>',
                'address_line_1' => '12 Demo Street',
                'country_code' => 'US',
                'check_in_time' => '14:00',
                'check_out_time' => '11:00',
                'currency' => 'USD',
            ]
        );

        $property->forceFill([
            'vendor_profile_id' => $vendorId,
            'status' => $status,
            'published_at' => $status === PropertyStatus::Published->value ? ($property->published_at ?? now()) : null,
            ...$overrides,
        ])->save();

        if ($amenityIds !== []) {
            $property->amenities()->syncWithoutDetaching($amenityIds);
        }

        return $property->fresh();
    }

    /**
     * Demo room types with beds, room amenities and a couple of physical
     * units. Idempotent via per-property slugs.
     */
    protected function rooms(Property $harbour, Property $lakeside): void
    {
        $service = app(HotelRoomService::class);
        $bedId = fn (string $slug): int => HotelBedType::where('slug', $slug)->firstOrFail()->id;
        $roomAmenities = HotelAmenity::whereIn('slug', ['television', 'private-bathroom', 'balcony', 'coffee-maker'])
            ->where('is_active', true)
            ->whereIn('scope', ['room', 'both'])
            ->pluck('id')
            ->all();

        $specs = [
            [$harbour, 'demo-standard-double', 'Demo Standard Double', ['king' => 1], 2, 0, 2, 'aggregate', 8],
            [$harbour, 'demo-deluxe-king', 'Demo Deluxe King', ['king' => 1, 'single' => 1], 3, 1, 4, 'aggregate', 6],
            [$harbour, 'demo-family-suite', 'Demo Family Suite', ['king' => 1, 'single' => 2], 4, 2, 6, 'units', null],
            [$lakeside, 'demo-lakeside-standard', 'Demo Standard Room', ['double' => 1], 2, 1, 3, 'aggregate', 4],
            [$lakeside, 'demo-pool-view-suite', 'Demo Pool View Suite', ['queen' => 1, 'sofa-bed' => 1], 3, 1, 4, 'aggregate', 2],
        ];

        foreach ($specs as [$property, $slug, $name, $beds, $adults, $children, $occupancy, $mode, $total]) {
            $roomType = HotelRoomType::firstOrCreate(
                ['property_id' => $property->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'short_description' => 'A generic demo room type.',
                    'max_adults' => $adults,
                    'max_children' => $children,
                    'max_occupancy' => $occupancy,
                    'size_value' => 28,
                    'size_unit' => 'sqm',
                    'inventory_mode' => $mode,
                    'total_units' => $total,
                    'status' => RoomTypeStatus::Active->value,
                ]
            );

            $bedRows = [];

            foreach ($beds as $bedSlug => $quantity) {
                $bedRows[] = ['bed_type_id' => $bedId($bedSlug), 'quantity' => $quantity];
            }

            $service->syncBeds($roomType, $bedRows);
            $service->syncRoomAmenities($roomType, $roomAmenities);
            $service->refreshBedSummary($roomType->fresh());

            if ($mode === HotelRoomType::INVENTORY_UNITS) {
                foreach (['Suite A', 'Suite B'] as $unitName) {
                    HotelRoomUnit::firstOrCreate(
                        ['property_id' => $property->id, 'unit_name' => $unitName],
                        ['room_type_id' => $roomType->id, 'status' => HotelRoomUnit::STATUS_ACTIVE]
                    );
                }
            }
        }
    }

    /**
     * Demo custom field schema + generic values (12B.2.1).
     *
     * Idempotent: definitions keyed by (entity_type, key); values flow
     * through the same validate/save service vendors use, so re-running
     * refreshes values without duplicating rows. No client branding.
     */
    protected function customFields(Property $harbour, Property $lakeside): void
    {
        $service = app(HotelCustomFieldService::class);

        $airport = HotelCustomFieldDefinition::firstOrCreate(
            ['entity_type' => HotelCustomFieldDefinition::ENTITY_PROPERTY, 'key' => 'nearest_airport'],
            [
                'name' => 'Nearest Airport',
                'field_type' => HotelCustomFieldDefinition::TYPE_TEXT,
                'group_name' => 'Additional Information',
                'help_text' => 'Distance to the closest airport.',
                'placeholder' => 'e.g. 12 km',
                'is_active' => true,
                'show_on_frontend' => true,
            ]
        );

        $languages = HotelCustomFieldDefinition::firstOrCreate(
            ['entity_type' => HotelCustomFieldDefinition::ENTITY_PROPERTY, 'key' => 'languages_spoken'],
            [
                'name' => 'Languages Spoken',
                'field_type' => HotelCustomFieldDefinition::TYPE_MULTISELECT,
                'group_name' => 'Additional Information',
                'is_active' => true,
                'show_on_frontend' => true,
                'options' => [
                    ['value' => 'english', 'label' => 'English'],
                    ['value' => 'hindi', 'label' => 'Hindi'],
                    ['value' => 'french', 'label' => 'French'],
                    ['value' => 'spanish', 'label' => 'Spanish'],
                ],
            ]
        );

        $charging = HotelCustomFieldDefinition::firstOrCreate(
            ['entity_type' => HotelCustomFieldDefinition::ENTITY_PROPERTY, 'key' => 'ev_charging'],
            [
                'name' => 'EV Charging',
                'field_type' => HotelCustomFieldDefinition::TYPE_BOOLEAN,
                'group_name' => 'Accessibility',
                'is_active' => true,
                'show_on_frontend' => true,
            ]
        );

        $viewType = HotelCustomFieldDefinition::firstOrCreate(
            ['entity_type' => HotelCustomFieldDefinition::ENTITY_ROOM_TYPE, 'key' => 'view_type'],
            [
                'name' => 'View Type',
                'field_type' => HotelCustomFieldDefinition::TYPE_SELECT,
                'group_name' => 'Additional Room Details',
                'is_active' => true,
                'show_on_frontend' => true,
                'options' => [
                    ['value' => 'sea-view', 'label' => 'Sea View'],
                    ['value' => 'garden-view', 'label' => 'Garden View'],
                    ['value' => 'city-view', 'label' => 'City View'],
                    ['value' => 'pool-view', 'label' => 'Pool View'],
                ],
            ]
        );

        $bathroom = HotelCustomFieldDefinition::firstOrCreate(
            ['entity_type' => HotelCustomFieldDefinition::ENTITY_ROOM_TYPE, 'key' => 'bathroom_type'],
            [
                'name' => 'Bathroom Type',
                'field_type' => HotelCustomFieldDefinition::TYPE_SELECT,
                'group_name' => 'Additional Room Details',
                'is_active' => true,
                'show_on_frontend' => true,
                'options' => [
                    ['value' => 'private-ensuite', 'label' => 'Private Ensuite'],
                    ['value' => 'bathtub', 'label' => 'Bathtub'],
                    ['value' => 'shower', 'label' => 'Shower'],
                    ['value' => 'shared', 'label' => 'Shared'],
                ],
            ]
        );

        $service->saveValues('property', $harbour->id, $service->validateValues('property', [
            $airport->id => '12 km',
            $languages->id => ['english', 'hindi'],
            $charging->id => true,
        ], $harbour->property_type_id));

        $service->saveValues('property', $lakeside->id, $service->validateValues('property', [
            $airport->id => '25 km',
            $languages->id => ['english'],
        ], $lakeside->property_type_id));

        $roomId = fn (Property $property, string $slug): ?int => HotelRoomType::where('property_id', $property->id)
            ->where('slug', $slug)->value('id');

        foreach ([
            [$harbour, 'demo-deluxe-king', [$viewType->id => 'sea-view', $bathroom->id => 'private-ensuite']],
            [$harbour, 'demo-family-suite', [$viewType->id => 'garden-view', $bathroom->id => 'bathtub']],
            [$lakeside, 'demo-pool-view-suite', [$viewType->id => 'pool-view', $bathroom->id => 'shower']],
        ] as [$property, $slug, $raw]) {
            $id = $roomId($property, $slug);

            if ($id !== null) {
                $service->saveValues('room_type', $id, $service->validateValues('room_type', $raw));
            }
        }
    }

    /**
     * Sparse demo inventory (12B.3): a couple of blocked/override/
     * stop-sell rows only. Normal dates stay row-free by design.
     */
    protected function inventory(Property $harbour, Property $lakeside): void
    {
        $base = now()->addDays(30)->startOfDay();

        $rows = [
            [$harbour->id, 'demo-standard-double', 2, ['blocked_units' => 2, 'note' => 'Demo maintenance hold']],
            [$harbour->id, 'demo-deluxe-king', 5, ['stop_sell' => true, 'note' => 'Demo private event']],
            [$lakeside->id, 'demo-pool-view-suite', 9, ['capacity_override' => 1, 'note' => 'Demo partial closure']],
        ];

        foreach ($rows as [$propertyId, $slug, $offset, $state]) {
            $roomId = HotelRoomType::where('property_id', $propertyId)->where('slug', $slug)->value('id');

            if ($roomId === null) {
                continue;
            }

            HotelRoomInventory::updateOrCreate(
                ['hotel_room_type_id' => $roomId, 'inventory_date' => $base->copy()->addDays($offset)->toDateString()],
                $state + ['source' => HotelRoomInventory::SOURCE_SYSTEM]
            );
        }
    }

    /**
     * Demo rate plans, one seasonal rule, one daily override, one
     * minimum stay and generic tax/fee rules (12B.4). Idempotent via
     * natural keys; sparse rows only, no generated calendars.
     */
    protected function pricing(Property $harbour, Property $lakeside): void
    {
        $roomId = fn (Property $property, string $slug): ?int => HotelRoomType::where('property_id', $property->id)
            ->where('slug', $slug)->value('id');

        $kingId = $roomId($harbour, 'demo-deluxe-king');

        if ($kingId === null) {
            return;
        }

        $plans = [
            ['flex-room-only', 'Demo Flexible Room Only', HotelRatePlan::MEAL_ROOM_ONLY, HotelRatePlan::CANCEL_FLEXIBLE, '100.00', []],
            ['breakfast-included', 'Demo Breakfast Included', HotelRatePlan::MEAL_BREAKFAST, HotelRatePlan::CANCEL_FLEXIBLE, '120.00', ['minimum_stay' => 2]],
            ['non-refundable', 'Demo Non-Refundable', HotelRatePlan::MEAL_ROOM_ONLY, HotelRatePlan::CANCEL_NON_REFUNDABLE, '90.00', []],
        ];

        $flexId = null;

        foreach ($plans as [$code, $name, $meal, $cancel, $rate, $extra]) {
            $plan = HotelRatePlan::firstOrCreate(
                ['property_id' => $harbour->id, 'code' => $code],
                [
                    'hotel_room_type_id' => $kingId,
                    'name' => $name,
                    'currency' => 'USD',
                    'meal_plan' => $meal,
                    'cancellation_mode' => $cancel,
                    'base_adults' => 2,
                    'base_children' => 0,
                    'base_rate' => $rate,
                    'extra_adult_rate' => '30.00',
                    'extra_child_rate' => '15.00',
                    'is_active' => true,
                    ...$extra,
                ]
            );

            if ($code === 'flex-room-only') {
                $flexId = $plan->id;
            }
        }

        if ($flexId !== null) {
            HotelRateSeason::firstOrCreate(
                ['hotel_rate_plan_id' => $flexId, 'name' => 'Demo Weekend Premium'],
                [
                    'start_date' => now()->addDays(20)->toDateString(),
                    'end_date' => now()->addDays(120)->toDateString(),
                    'adjustment_type' => HotelRateSeason::ADJUST_PERCENTAGE,
                    'adjustment_value' => '20.00',
                    'priority' => 10,
                    'applicable_weekdays' => [5, 6],
                    'is_active' => true,
                ]
            );

            HotelDailyRate::updateOrCreate(
                ['hotel_rate_plan_id' => $flexId, 'rate_date' => now()->addDays(40)->toDateString()],
                ['amount_override' => '180.00', 'source' => HotelDailyRate::SOURCE_SYSTEM]
            );
        }

        HotelChargeRule::firstOrCreate(
            ['property_id' => $harbour->id, 'name' => 'Demo VAT'],
            [
                'charge_type' => HotelChargeRule::TYPE_TAX,
                'calculation' => HotelChargeRule::CALC_PERCENTAGE,
                'value' => '10.00',
                'currency' => 'USD',
                'is_active' => true,
            ]
        );

        HotelChargeRule::firstOrCreate(
            ['property_id' => $harbour->id, 'name' => 'Demo City Fee'],
            [
                'charge_type' => HotelChargeRule::TYPE_FEE,
                'calculation' => HotelChargeRule::CALC_FIXED_NIGHT,
                'value' => '5.00',
                'currency' => 'USD',
                'is_active' => true,
            ]
        );
    }
}
