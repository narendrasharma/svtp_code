<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\State;
use App\Models\Tag;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourClassificationRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tour_can_be_classified_with_destinations_places_and_tags(): void
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create([
            'state_id' => $state->id,
            'name' => 'Mathura',
            'slug' => 'mathura',
            'is_spiritual_hub' => true,
        ]);
        $tour = TourPackage::create([
            'title' => 'Vrindavan Darshan',
            'slug' => 'vrindavan-darshan',
            'city_id' => $city->id,
            'duration_days' => 1,
            'duration_nights' => 0,
            'price' => 2500,
            'is_active' => true,
        ]);
        $destination = Destination::create([
            'city_id' => $city->id,
            'name' => 'Vrindavan',
            'slug' => 'vrindavan',
        ]);
        $place = Place::create([
            'destination_id' => $destination->id,
            'name' => 'Banke Bihari Temple',
            'slug' => 'banke-bihari-temple',
        ]);
        $tag = Tag::create(['name' => 'Family', 'slug' => 'family']);

        $tour->destinations()->attach($destination);
        $tour->places()->attach($place);
        $tour->tags()->attach($tag);

        $this->assertTrue($city->destinations->contains($destination));
        $this->assertTrue($destination->places->contains($place));
        $this->assertTrue($destination->tourPackages->contains($tour));
        $this->assertTrue($place->tourPackages->contains($tour));
        $this->assertTrue($tag->tourPackages->contains($tour));
        $this->assertTrue($tour->destinations->contains($destination));
        $this->assertTrue($tour->places->contains($place));
        $this->assertTrue($tour->tags->contains($tag));
    }
}
