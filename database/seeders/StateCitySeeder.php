<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StateCitySeeder extends Seeder
{
    public function run(): void
    {
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
                ['name' => $stateName]
            );

            foreach ($cities as $cityName => $isHub) {
                City::firstOrCreate(
                    ['slug' => Str::slug($cityName)],
                    ['name' => $cityName, 'state_id' => $state->id, 'is_spiritual_hub' => $isHub]
                );
            }
        }
    }
}
