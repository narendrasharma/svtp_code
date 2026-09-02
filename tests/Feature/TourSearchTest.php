<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\State;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TourSearchTest extends TestCase
{
    use RefreshDatabase;

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

    private function createPackage(City $city, string $title): void
    {
        TourPackage::create([
            'title' => $title, 'slug' => str($title)->slug(), 'city_id' => $city->id,
            'duration_days' => 1, 'duration_nights' => 0, 'price' => 3000, 'is_active' => true,
        ]);
    }
}
