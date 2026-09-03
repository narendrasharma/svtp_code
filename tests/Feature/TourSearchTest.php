<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\State;
use App\Models\Tag;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TourSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_search_filters_by_database_destination_and_preserves_trip_details(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $vrindavan = City::create(['state_id' => $state->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);
        $agra = City::create(['state_id' => $state->id, 'name' => 'Agra', 'slug' => 'agra']);
        $this->createPackage($vrindavan, 'Vrindavan Darshan');
        $this->createPackage($agra, 'Agra Tour');

        $response = $this->get('/packages?city=vrindavan&pickup_address=Delhi%20Airport&pickup_place_id=place-1&pickup_lat=28.5&pickup_lng=77.1&date=2026-09-15&adults=3&children=1');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Index')
            ->has('packages.data', 1)
            ->where('packages.data.0.title', 'Vrindavan Darshan')
            ->where('filters.pickup_address', 'Delhi Airport')
            ->where('filters.pickup_place_id', 'place-1')
            ->where('filters.adults', '3'));
    }

    public function test_search_filters_tours_by_linked_destination_slug(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create(['city_id' => $city->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);
        $linkedTour = $this->createPackage($city, 'Vrindavan Darshan');
        $this->createPackage($city, 'Mathura Heritage Tour');
        $linkedTour->destinations()->attach($destination);

        $this->get('/packages?destination=vrindavan&date=2026-09-15&adults=2&children=0')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $linkedTour->id)
                ->where('filters.destination', 'vrindavan'));
    }

    public function test_search_filters_tours_by_linked_destination_id(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create(['city_id' => $city->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);
        $linkedTour = $this->createPackage($city, 'Vrindavan Darshan');
        $this->createPackage($city, 'Mathura Heritage Tour');
        $linkedTour->destinations()->attach($destination);

        $this->get("/packages?destination_id={$destination->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $linkedTour->id)
                ->where('filters.destination_id', (string) $destination->id));
    }

    public function test_search_filters_tours_by_linked_place_slug(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $destination = Destination::create(['city_id' => $city->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);
        $place = Place::create([
            'destination_id' => $destination->id,
            'name' => 'Prem Mandir',
            'slug' => 'prem-mandir',
        ]);
        $linkedTour = $this->createPackage($city, 'Prem Mandir Darshan');
        $this->createPackage($city, 'Mathura Heritage Tour');
        $linkedTour->places()->attach($place);

        $this->get('/packages?place=prem-mandir')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $linkedTour->id)
                ->where('filters.place', 'prem-mandir')
                ->has('places', 1)
                ->where('places.0.slug', 'prem-mandir')
                ->where('places.0.destination.name', 'Vrindavan'));
    }

    public function test_search_filters_by_any_selected_active_tag_and_lists_useful_tags(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $family = Tag::create(['name' => 'Family', 'slug' => 'family', 'is_active' => true]);
        $heritage = Tag::create(['name' => 'Heritage', 'slug' => 'heritage', 'is_active' => true]);
        Tag::create(['name' => 'Unused', 'slug' => 'unused', 'is_active' => true]);
        Tag::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $familyTour = $this->createPackage($city, 'Family Braj Tour');
        $heritageTour = $this->createPackage($city, 'Heritage Walk');
        $this->createPackage($city, 'Unrelated Tour');
        $familyTour->tags()->attach($family);
        $heritageTour->tags()->attach($heritage);

        $this->get('/packages?tags%5B0%5D=family&tags%5B1%5D=heritage')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 2)
                ->where('filters.tags', ['family', 'heritage'])
                ->has('tags', 2)
                ->where('tags.0.slug', 'family')
                ->where('tags.1.slug', 'heritage'));
    }

    public function test_inactive_tags_cannot_filter_public_tours(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $hidden = Tag::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $tour = $this->createPackage($city, 'Hidden Tour');
        $tour->tags()->attach($hidden);

        $this->get('/packages?tags%5B0%5D=hidden')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 0)
                ->has('tags', 0));
    }

    public function test_home_search_receives_only_internal_destination_options(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create([
            'state_id' => $state->id,
            'name' => 'Mathura',
            'slug' => 'mathura',
            'is_spiritual_hub' => true,
        ]);
        $destination = Destination::create(['city_id' => $city->id, 'name' => 'Vrindavan', 'slug' => 'vrindavan']);

        $this->get(route('home', absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('destinations', 1)
                ->where('destinations.0.id', $destination->id)
                ->where('destinations.0.slug', 'vrindavan')
                ->where('destinations.0.city.name', 'Mathura')
                ->where('destinations.0.city.state.name', 'Uttar Pradesh')
                ->missing('cities'));
    }

    public function test_home_featured_tours_include_card_fields_and_resolve_by_slug(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create(['state_id' => $state->id, 'name' => 'Mathura', 'slug' => 'mathura']);
        $featuredTour = TourPackage::create([
            'title' => 'Mathura Vrindavan Darshan',
            'slug' => 'mathura-vrindavan-darshan',
            'city_id' => $city->id,
            'duration_days' => 3,
            'duration_nights' => 2,
            'price' => 6500,
            'discounted_price' => 5500,
            'is_featured' => true,
            'is_active' => true,
        ]);
        $this->createPackage($city, 'Non-featured Tour');
        Cache::put('home.featured_packages', [['title' => 'Stale cached tour']]);

        $this->get(route('home', absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('featured', 1)
                ->where('featured.0.id', $featuredTour->id)
                ->where('featured.0.title', 'Mathura Vrindavan Darshan')
                ->where('featured.0.slug', 'mathura-vrindavan-darshan')
                ->where('featured.0.duration_days', 3)
                ->where('featured.0.duration_nights', 2)
                ->where('featured.0.price', 6500)
                ->where('featured.0.discounted_price', 5500)
                ->where('featured.0.cover_image', null)
                ->where('featured.0.city.name', 'Mathura'));

        $this->get(route('packages.show', $featuredTour, absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Show')
                ->where('package.slug', 'mathura-vrindavan-darshan'));
    }

    private function createPackage(City $city, string $title): TourPackage
    {
        return TourPackage::create([
            'title' => $title, 'slug' => str($title)->slug(), 'city_id' => $city->id,
            'duration_days' => 1, 'duration_nights' => 0, 'price' => 3000, 'is_active' => true,
        ]);
    }
}
