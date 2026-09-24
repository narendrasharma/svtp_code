<?php

namespace Database\Seeders;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Enums\TripType;
use App\Models\City;
use App\Models\Country;
use App\Models\Destination;
use App\Models\HotelAmenity;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\Page;
use App\Models\Place;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Review;
use App\Models\State;
use App\Models\Tag;
use App\Models\TaxiRateCard;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Manual, non-destructive marketplace catalogue for local/demo environments.
 *
 * Run with:
 *   php artisan db:seed --class=MarketplaceDemoSeeder
 *
 * Existing records are preserved. The existing catalog, hotel and taxi demo
 * seeders are reused, then this seeder adds generic marketplace variations,
 * local media and stable relationships around their data.
 */
class MarketplaceDemoSeeder extends Seeder
{
    /** @var array<string, string> */
    private const FIXTURES = [
        'landscape' => 'agra-fort-landscape.jpg',
        'standard' => 'varanasi-ghats-standard.jpg',
        'portrait' => 'heritage-arch-portrait.jpg',
        'wide' => 'heritage-wide.jpg',
    ];

    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            StateCitySeeder::class,
            HotelDemoSeeder::class,
        ]);

        $geography = $this->seedExpandedGeography();
        $this->seedTours($geography);
        $this->seedHotels($geography);
        $this->seedTaxiCatalog();
        $this->seedContentPages();
        $this->seedAdminAccount();

        $this->command?->info('Marketplace demo data ready. Existing records were preserved.');
    }

    /** @return array{cities:array<string, City>, destinations:array<string, Destination>, places:array<string, Place>} */
    private function seedExpandedGeography(): array
    {
        $india = Country::firstOrCreate(
            ['iso2' => 'IN'],
            ['name' => 'India', 'iso3' => 'IND', 'phone_code' => '+91', 'currency_code' => 'INR']
        );

        $cityRows = [
            ['state' => 'Rajasthan', 'city' => 'Jaipur'],
            ['state' => 'Rajasthan', 'city' => 'Udaipur'],
            ['state' => 'Rajasthan', 'city' => 'Jodhpur'],
            ['state' => 'Uttarakhand', 'city' => 'Rishikesh'],
            ['state' => 'Uttarakhand', 'city' => 'Haridwar'],
            ['state' => 'Goa', 'city' => 'Panaji'],
            ['state' => 'Maharashtra', 'city' => 'Mumbai'],
            ['state' => 'Uttar Pradesh', 'city' => 'Agra'],
            ['state' => 'Uttar Pradesh', 'city' => 'Varanasi'],
        ];

        $cities = [];

        foreach ($cityRows as $row) {
            $state = State::firstOrCreate(
                ['slug' => Str::slug($row['state'])],
                ['name' => $row['state'], 'country_id' => $india->id]
            );

            $cities[Str::slug($row['city'])] = City::firstOrCreate(
                ['slug' => Str::slug($row['city'])],
                ['name' => $row['city'], 'state_id' => $state->id, 'country_id' => $india->id]
            );
        }

        $destinationRows = [
            ['slug' => 'agra', 'name' => 'Agra', 'city' => 'agra', 'description' => 'A Mughal heritage city shaped by the Taj Mahal, Agra Fort and the Yamuna riverfront.', 'place' => ['agra-fort', 'Agra Fort']],
            ['slug' => 'jaipur', 'name' => 'Jaipur', 'city' => 'jaipur', 'description' => 'A gracious Rajasthan capital shaped by palace courtyards, craft markets and hilltop forts.', 'place' => ['hawa-mahal', 'Hawa Mahal']],
            ['slug' => 'udaipur', 'name' => 'Udaipur', 'city' => 'udaipur', 'description' => 'A lakeside heritage city of whitewashed palaces, old lanes and sunset boat rides.', 'place' => ['city-palace-udaipur', 'City Palace Udaipur']],
            ['slug' => 'jodhpur', 'name' => 'Jodhpur', 'city' => 'jodhpur', 'description' => 'The Blue City, anchored by Mehrangarh Fort and a lively old quarter.', 'place' => ['mehrangarh-fort', 'Mehrangarh Fort']],
            ['slug' => 'rishikesh', 'name' => 'Rishikesh', 'city' => 'rishikesh', 'description' => 'A riverside base for yoga, forest walks, rafting and the evening lights of Triveni Ghat.', 'place' => ['triveni-ghat', 'Triveni Ghat']],
            ['slug' => 'haridwar', 'name' => 'Haridwar', 'city' => 'haridwar', 'description' => 'A gateway to the Himalayas known for Har Ki Pauri and the Ganga aarti.', 'place' => ['har-ki-pauri', 'Har Ki Pauri']],
            ['slug' => 'goa', 'name' => 'Goa', 'city' => 'panaji', 'description' => 'A relaxed coastal destination with heritage quarters, beaches and a generous food culture.', 'place' => ['fontainhas', 'Fontainhas']],
            ['slug' => 'mumbai', 'name' => 'Mumbai', 'city' => 'mumbai', 'description' => 'India’s energetic west-coast metropolis, where architecture, food and the sea meet.', 'place' => ['gateway-of-india', 'Gateway of India']],
            ['slug' => 'varanasi', 'name' => 'Varanasi', 'city' => 'varanasi', 'description' => 'An ancient riverside city with living ghats, music, craft and a layered cultural history.', 'place' => ['assi-ghat', 'Assi Ghat']],
        ];

        $destinations = [];
        $places = [];

        foreach ($destinationRows as $row) {
            $city = $cities[$row['city']];
            $destination = Destination::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'country_id' => $india->id,
                    'state_id' => $city->state_id,
                    'city_id' => $city->id,
                    'destination_type' => Destination::TYPE_CITY,
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'meta_description' => $row['description'],
                    'is_active' => true,
                ]
            );
            if ($destination->excerpt === null) {
                $destination->update(['excerpt' => $row['description']]);
            }
            $destinations[$row['slug']] = $destination;

            [$placeSlug, $placeName] = $row['place'];
            $places[$placeSlug] = Place::firstOrCreate(
                ['slug' => $placeSlug],
                [
                    'destination_id' => $destination->id,
                    'name' => $placeName,
                    'description' => 'A genuine place to explore while visiting '.$row['name'].'.',
                    'is_active' => true,
                ]
            );
            if ($places[$placeSlug]->excerpt === null) {
                $places[$placeSlug]->update(['excerpt' => 'A concise guide to '.$placeName.' and what to notice while visiting '.$row['name'].'.']);
            }
        }

        return ['cities' => $cities, 'destinations' => $destinations, 'places' => $places];
    }

    /** @param array{cities:array<string, City>, destinations:array<string, Destination>, places:array<string, Place>} $geography */
    private function seedTours(array $geography): void
    {
        $categories = [];
        foreach ([
            'heritage' => 'Heritage journeys',
            'spiritual' => 'Spiritual journeys',
            'city-breaks' => 'City breaks',
            'nature' => 'Nature and riverside escapes',
        ] as $slug => $description) {
            $categories[$slug] = TourCategory::firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::title(str_replace('-', ' ', $slug)), 'description' => $description, 'is_active' => true]
            );
        }

        $tags = [];
        foreach (['heritage', 'same-day', 'family', 'weekend', 'private', 'riverside', 'spiritual'] as $slug) {
            $tags[$slug] = Tag::firstOrCreate(['slug' => $slug], ['name' => Str::title(str_replace('-', ' ', $slug)), 'is_active' => true]);
        }

        $rows = [
            ['same-day-agra-heritage-tour', 'Same Day Agra Heritage Tour', 'agra', 1, 0, 3499, 'heritage', ['agra'], ['agra-fort'], ['same-day', 'heritage']],
            ['jaipur-ajmer-discovery', '3 Days Jaipur & Ajmer Discovery', 'jaipur', 3, 2, 8999, 'heritage', ['jaipur'], ['hawa-mahal'], ['heritage', 'family']],
            ['udaipur-heritage-escape', '4 Days Udaipur Heritage Escape', 'udaipur', 4, 3, 12999, 'heritage', ['udaipur'], ['city-palace-udaipur'], ['heritage', 'weekend']],
            ['haridwar-rishikesh-journey', 'Haridwar & Rishikesh Spiritual Journey', 'rishikesh', 3, 2, 7499, 'spiritual', ['haridwar', 'rishikesh'], ['har-ki-pauri', 'triveni-ghat'], ['spiritual', 'family']],
            ['varanasi-ghats-sarnath', 'Varanasi Ghats & Sarnath Experience', 'varanasi', 2, 1, 5999, 'spiritual', ['varanasi'], ['assi-ghat'], ['spiritual', 'private']],
            ['jaipur-forts-city-palaces', 'Jaipur Forts, Markets and City Palaces', 'jaipur', 2, 1, 6499, 'heritage', ['jaipur'], ['hawa-mahal'], ['heritage', 'private']],
            ['udaipur-lakes-old-city', 'Udaipur Lakes and Old City Weekend', 'udaipur', 2, 1, 6999, 'city-breaks', ['udaipur'], ['city-palace-udaipur'], ['weekend', 'family']],
            ['rishikesh-riverside-escape', 'Rishikesh Riverside and Rajaji Escape', 'rishikesh', 2, 1, 6799, 'nature', ['rishikesh'], ['triveni-ghat'], ['riverside', 'weekend']],
            ['jodhpur-mehrangarh-blue-city', 'Jodhpur Mehrangarh and Blue City Trail', 'jodhpur', 2, 1, 7299, 'heritage', ['jodhpur'], ['mehrangarh-fort'], ['heritage', 'private']],
            ['golden-triangle-heritage-circuit', 'Delhi, Agra and Jaipur Heritage Circuit', 'jaipur', 5, 4, 17999, 'heritage', ['agra', 'jaipur'], ['agra-fort', 'hawa-mahal'], ['heritage', 'family']],
        ];

        foreach ($rows as $index => [$slug, $title, $citySlug, $days, $nights, $price, $categorySlug, $destinationSlugs, $placeSlugs, $tagSlugs]) {
            $itinerary = [];
            for ($day = 1; $day <= $days; $day++) {
                $itinerary[] = [
                    'day' => $day,
                    'title' => $day === 1 ? 'Arrival and orientation' : 'Heritage and local experiences',
                    'description' => $day === 1
                        ? 'Meet your local coordinator, settle in and begin the first gentle exploration.'
                        : 'Enjoy a paced day of landmarks, neighbourhood walks and time for independent discovery.',
                ];
            }

            $coverPath = $index === 9 ? null : $this->copyFixture(array_values(self::FIXTURES)[$index % count(self::FIXTURES)], 'demo/marketplace/tours/'.$slug.'/cover.'.pathinfo(array_values(self::FIXTURES)[$index % count(self::FIXTURES)], PATHINFO_EXTENSION));
            $galleryPath = $coverPath === null ? [] : [$this->copyFixture('standard', 'demo/marketplace/tours/'.$slug.'/gallery-1.jpg')];

            $tour = TourPackage::firstOrCreate(
                ['slug' => 'marketplace-demo-'.$slug],
                [
                    'title' => $title,
                    'city_id' => $geography['cities'][$citySlug]->id,
                    'duration_days' => $days,
                    'duration_nights' => $nights,
                    'price' => $price,
                    'overview' => 'A carefully paced marketplace demo itinerary for travellers who want clear inclusions, practical timing and room to explore.',
                    'day_wise_itinerary' => $itinerary,
                    'inclusions' => ['Private air-conditioned vehicle', 'Local coordinator', 'Daily breakfast on multi-day departures'],
                    'exclusions' => ['Monument entry fees', 'Personal purchases', 'Travel insurance'],
                    'gallery' => $galleryPath,
                    'cover_image' => $coverPath,
                    'is_featured' => $index < 4,
                    'is_active' => true,
                    'category' => $categorySlug,
                    'category_id' => $categories[$categorySlug]->id,
                    'meta_description' => $title.' with transparent pricing and a practical day-wise plan.',
                    'moderation_status' => 'approved',
                    'booking_enabled' => true,
                ]
            );

            $tour->destinations()->syncWithoutDetaching(array_map(fn (string $slug): int => $geography['destinations'][$slug]->id, $destinationSlugs));
            $tour->places()->syncWithoutDetaching(array_map(fn (string $slug): int => $geography['places'][$slug]->id, $placeSlugs));
            $tour->tags()->syncWithoutDetaching(array_map(fn (string $slug): int => $tags[$slug]->id, $tagSlugs));

            if ($tour->cover_image === null && $coverPath !== null) {
                $tour->forceFill(['cover_image' => $coverPath])->save();
            }

            Review::firstOrCreate(
                ['package_id' => $tour->id, 'reviewer_email' => 'traveller-'.$index.'@example.test'],
                [
                    'reviewer_name' => ['Aarav Mehta', 'Maya Shah', 'Daniel Reed'][$index % 3],
                    'rating' => $index % 3 === 1 ? 4 : 5,
                    'comment' => $index % 3 === 1
                        ? 'The route was well paced and the local guide gave us useful context without rushing the visits.'
                        : 'Clear communication, comfortable transfers and enough time to enjoy each stop at our own pace.',
                    'is_approved' => true,
                ]
            );
        }

        foreach ($geography['destinations'] as $slug => $destination) {
            if ($destination->image) {
                continue;
            }

            $destination->forceFill(['image' => Storage::disk('public')->url($this->copyFixture($slug === 'udaipur' ? 'portrait' : 'standard', 'demo/marketplace/destinations/'.$slug.'.jpg'))])->save();
        }

        foreach ($geography['places'] as $slug => $place) {
            if ($place->image) {
                continue;
            }

            $place->forceFill(['image' => Storage::disk('public')->url($this->copyFixture($slug === 'mehrangarh-fort' ? 'portrait' : 'landscape', 'demo/marketplace/places/'.$slug.'.jpg'))])->save();
        }
    }

    /** @param array{cities:array<string, City>, destinations:array<string, Destination>, places:array<string, Place>} $geography */
    private function seedHotels(array $geography): void
    {
        $vendor = VendorProfile::where('slug', 'demo-stays')->first();
        $type = PropertyType::whereIn('slug', ['hotel', 'resort', 'homestay'])->first();

        if ($vendor === null || $type === null) {
            return;
        }

        $amenityIds = HotelAmenity::whereIn('slug', ['free-wi-fi', 'parking', 'air-conditioning', 'restaurant'])->pluck('id')->all();
        $properties = [
            ['aravali-heritage-retreat', 'Aravali Heritage Retreat', 'agra', 'agra', 4, 4200],
            ['riverstone-rishikesh', 'Riverstone Rishikesh', 'rishikesh', 'rishikesh', 3, 3600],
            ['amber-courtyard-hotel', 'Amber Courtyard Hotel', 'jaipur', 'jaipur', 5, 6800],
        ];

        foreach ($properties as $index => [$slug, $name, $citySlug, $destinationSlug, $stars, $rate]) {
            $city = $geography['cities'][$citySlug];
            $property = Property::firstOrCreate(
                ['slug' => 'marketplace-demo-'.$slug],
                [
                    'property_type_id' => $type->id,
                    'name' => $name,
                    'short_description' => 'A fictional, independently operated stay for marketplace demonstration.',
                    'description' => '<p>Comfortable rooms, thoughtful local recommendations and a convenient base for exploring the surrounding heritage district.</p>',
                    'city_id' => $city->id,
                    'state_id' => $city->state_id,
                    'country_id' => $city->country_id,
                    'destination_id' => $geography['destinations'][$destinationSlug]->id,
                    'country_code' => 'IN',
                    'address_line_1' => '12 Heritage Market Road',
                    'postal_code' => '282001',
                    'check_in_time' => '14:00',
                    'check_out_time' => '11:00',
                    'currency' => 'INR',
                    'star_rating' => $stars,
                    'is_featured' => $index === 0,
                ]
            );

            if ($property->wasRecentlyCreated) {
                $property->forceFill([
                    'vendor_profile_id' => $vendor->id,
                    'status' => PropertyStatus::Published->value,
                    'published_at' => now(),
                ])->save();
            }

            $property->amenities()->syncWithoutDetaching($amenityIds);
            $room = HotelRoomType::firstOrCreate(
                ['property_id' => $property->id, 'slug' => 'marketplace-deluxe-room'],
                [
                    'name' => $index === 1 ? 'Riverside Deluxe Room with Balcony' : 'Deluxe Heritage Room',
                    'short_description' => 'A bright room with practical storage, private bathroom and a comfortable work surface.',
                    'max_adults' => 2,
                    'max_children' => 1,
                    'max_occupancy' => 3,
                    'size_value' => 30,
                    'size_unit' => 'sqm',
                    'inventory_mode' => HotelRoomType::INVENTORY_AGGREGATE,
                    'total_units' => 6,
                    'status' => RoomTypeStatus::Active->value,
                    'is_featured' => true,
                ]
            );

            foreach ([['flexible', 'Flexible Stay', $rate], ['breakfast', 'Breakfast Included', $rate + 650]] as [$code, $planName, $planRate]) {
                HotelRatePlan::firstOrCreate(
                    ['property_id' => $property->id, 'code' => 'marketplace-'.$code],
                    [
                        'hotel_room_type_id' => $room->id,
                        'name' => $planName,
                        'currency' => 'INR',
                        'meal_plan' => $code === 'breakfast' ? HotelRatePlan::MEAL_BREAKFAST : HotelRatePlan::MEAL_ROOM_ONLY,
                        'cancellation_mode' => HotelRatePlan::CANCEL_FLEXIBLE,
                        'base_adults' => 2,
                        'base_children' => 1,
                        'base_rate' => $planRate,
                        'extra_adult_rate' => 900,
                        'extra_child_rate' => 500,
                        'is_active' => true,
                    ]
                );
            }

            $imageCount = $index === 0 ? 1 : 3;
            foreach (array_slice(array_keys(self::FIXTURES), $index, $imageCount) as $imageIndex => $fixtureKey) {
                $path = $this->copyFixture(self::FIXTURES[$fixtureKey], 'demo/marketplace/hotels/'.$property->slug.'/gallery-'.($imageIndex + 1).'.jpg');
                $property->images()->firstOrCreate(
                    ['path' => $path],
                    ['alt_text' => $name.' guest area', 'sort_order' => $imageIndex, 'is_primary' => ! $property->images()->exists()]
                );
            }

            $roomPath = $this->copyFixture($index === 2 ? 'portrait' : 'standard', 'demo/marketplace/rooms/'.$room->id.'/room-1.jpg');
            $room->images()->firstOrCreate(
                ['path' => $roomPath],
                ['alt_text' => $room->name, 'sort_order' => 0, 'is_primary' => ! $room->images()->exists()]
            );
        }
    }

    private function seedTaxiCatalog(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo-cabs@example.com'],
            ['name' => 'Demo Cabs Owner', 'password' => Hash::make('password'), 'role' => 'vendor']
        );
        $vendor = VendorProfile::firstOrCreate(
            ['slug' => 'demo-cabs'],
            ['user_id' => $user->id, 'business_name' => 'Demo Cabs', 'entity_type' => 'company', 'email' => $user->email, 'city' => 'Jaipur', 'is_active' => true]
        );

        foreach ([
            ['marketplace-sedan', 'City Sedan', 4, 2, 1],
            ['marketplace-suv', 'Family SUV', 6, 3, 2],
        ] as [$slug, $name, $capacity, $luggage, $sort]) {
            $type = VehicleType::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => 'Comfortable private transfers for city and intercity journeys.', 'passenger_capacity' => $capacity, 'luggage_capacity' => $luggage, 'is_active' => true, 'sort_order' => $sort]
            );
            $type->fill(['image_path' => $this->copyFixture($sort === 1 ? 'landscape' : 'portrait', 'demo/marketplace/taxi/'.$slug.'.jpg')])->save();

            Vehicle::firstOrCreate(
                ['reference' => 'VEH-MARKETPLACE-0'.$sort],
                ['vendor_profile_id' => $vendor->id, 'vehicle_type_id' => $type->id, 'name' => $name.' Transfer Vehicle', 'registration_number' => 'DEMO'.$sort.'001', 'passenger_capacity' => $capacity, 'luggage_capacity' => $luggage, 'status' => 'available', 'is_active' => true]
            );

            $card = TaxiRateCard::firstOrCreate(
                ['vendor_profile_id' => $vendor->id, 'vehicle_type_id' => $type->id, 'name' => 'Marketplace INR One Way', 'trip_type' => TripType::OneWay->value],
                ['currency' => 'INR', 'is_active' => true]
            );
            $card->rules()->firstOrCreate(['code' => TaxiRateRuleCode::BaseFare->value], ['calculation_type' => TaxiRateCalculationType::Fixed->value, 'amount' => $sort === 1 ? 900 : 1400]);
            $card->rules()->firstOrCreate(['code' => TaxiRateRuleCode::DistanceRate->value], ['calculation_type' => TaxiRateCalculationType::PerKilometer->value, 'amount' => $sort === 1 ? 16 : 22, 'included_quantity' => 0, 'unit' => 'km']);
        }
    }

    private function seedContentPages(): void
    {
        foreach ([
            ['about', 'About our marketplace', 'A clear, flexible marketplace for discovering stays, tours and local transfers across India.', '<p>Our marketplace helps travellers compare real stays, practical itineraries and dependable local transport in one place.</p>'],
            ['privacy-policy', 'Privacy Policy', 'How this marketplace handles account, enquiry and booking information.', '<h2>Information we use</h2><p>We use information needed to provide accounts, enquiries, reservations and customer support.</p>'],
            ['terms-and-conditions', 'Terms and Conditions', 'The terms for using this travel marketplace and its booking services.', '<h2>Using the marketplace</h2><p>Use accurate information, review the details shown before booking and contact support when a change is needed.</p>'],
        ] as [$slug, $title, $excerpt, $content]) {
            Page::firstOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'template' => 'default', 'excerpt' => $excerpt, 'content' => $content, 'is_active' => true, 'meta_title' => $title, 'meta_description' => $excerpt]
            );
        }
    }

    private function seedAdminAccount(): void
    {
        if (User::where('role', 'admin')->exists()) {
            $this->command?->line('Existing admin account detected; no password was changed.');

            return;
        }

        User::create([
            'name' => 'Marketplace Demo Admin',
            'email' => 'demo-admin@example.test',
            'password' => Hash::make('DemoAdmin123!'),
            'role' => 'admin',
        ]);

        $this->command?->warn('Created demo admin: demo-admin@example.test / DemoAdmin123!');
    }

    private function copyFixture(string $fixture, string $target): string
    {
        $fixture = self::FIXTURES[$fixture] ?? $fixture;
        $source = database_path('seeders/assets/marketplace/'.$fixture);
        if (! is_file($source)) {
            throw new \RuntimeException('Missing marketplace demo fixture: '.$source);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($target)) {
            $disk->put($target, file_get_contents($source));
        }

        return $target;
    }
}
