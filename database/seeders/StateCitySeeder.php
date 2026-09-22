<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StateCitySeeder extends Seeder
{
    public function run(): void
    {
        // Demo catalogue geography is India-specific by dataset (UP,
        // Uttarakhand, Rajasthan, Delhi). The country link is attached
        // explicitly — never guessed — so shared geography stays valid.
        $india = Country::firstOrCreate(
            ['iso2' => 'IN'],
            ['name' => 'India', 'iso3' => 'IND', 'phone_code' => '+91', 'currency_code' => 'INR']
        );

        $data = [
            'Uttar Pradesh' => [
                'Vrindavan' => true, 'Mathura' => true, 'Gokul' => true, 'Agra' => false,
                'Ayodhya' => true, 'Varanasi' => true, 'Naimisharanya' => true,
                'Lucknow' => false, 'Prayagraj' => true, 'Nandgaon' => true,
                'Barsana' => true, 'Govardhan' => true,
            ],
            'Uttarakhand' => [
                'Haridwar' => true, 'Rishikesh' => true, 'Mussoorie' => false,
            ],
            'Rajasthan' => [
                'Jaipur' => false, 'Mehandipur Balaji' => true, 'Khatu Shyam Ji' => true,
                'Salasar Balaji' => true,
            ],
            'Delhi' => [
                'Delhi' => false,
            ],
        ];

        foreach ($data as $stateName => $cities) {
            $state = State::firstOrCreate(
                ['slug' => Str::slug($stateName)],
                ['name' => $stateName, 'country_id' => $india->id]
            );

            if ($state->country_id === null) {
                $state->forceFill(['country_id' => $india->id])->save();
            }

            foreach ($cities as $cityName => $isHub) {
                $city = City::firstOrCreate(
                    ['slug' => Str::slug($cityName)],
                    ['name' => $cityName, 'state_id' => $state->id, 'country_id' => $india->id, 'is_spiritual_hub' => $isHub]
                );

                if ($city->country_id === null) {
                    $city->forceFill(['country_id' => $city->state?->country_id ?? $india->id])->save();
                }
            }
        }
    }
}
