<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\TourPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TourPackageSeeder extends Seeder
{
    public function run(): void
    {
        $vrindavan = City::where('slug', 'vrindavan')->first();

        $packages = [
            [
                'title' => 'Same-Day Darshan Tour',
                'duration_days' => 1,
                'duration_nights' => 0,
                'price' => 2499,
                'discounted_price' => 1999,
                'overview' => 'A same-day pilgrimage covering Gokul, Mathura and Vrindavan, with an optional Agra extension.',
                'category' => 'braj',
                'is_featured' => true,
                'day_wise_itinerary' => [
                    [
                        'day' => 1,
                        'title' => 'Gokul, Mathura & Vrindavan Darshan',
                        'points' => [
                            'Gokul: Nand Bhavan, Raman Reti, Raishkhan Samadhi, Brahmand Ghat, Chintaharan Mahadev, 84 Khamba',
                            'Mathura: Vishram Ghat, Shree Krishna Janmabhoomi, Dwarikadhish Temple',
                            'Vrindavan: Banke Bihari Ji, Radhaballabh Ji, Madan Mohan Ji, Nidhivan, ISKCON Temple, Prem Mandir',
                            'Optional Agra Add-on: Taj Mahal, Mahtab Bagh, Red Fort, Baby Taj',
                        ],
                    ],
                ],
                'inclusions' => ['AC private cab', 'Local guide', 'Darshan support'],
                'exclusions' => ['Meals', 'Temple donations', 'Agra add-on (optional, extra cost)'],
            ],
            [
                'title' => '1 Night / 2 Days Tour',
                'duration_days' => 2,
                'duration_nights' => 1,
                'price' => 4999,
                'discounted_price' => 4299,
                'overview' => 'A relaxed two-day Braj pilgrimage covering Gokul, Mathura and a full day in Vrindavan.',
                'category' => 'braj',
                'is_featured' => true,
                'day_wise_itinerary' => [
                    ['day' => 1, 'title' => 'Gokul & Mathura Exploration', 'points' => ['Gokul sightseeing', 'Mathura temple circuit']],
                    ['day' => 2, 'title' => 'Full-Day Vrindavan Tour', 'points' => [
                        'Banke Bihari Ji, Radhaballabh Ji, Madan Mohan Ji',
                        'Vaishno Devi Temple, Chardham Temple',
                        'Nidhivan, ISKCON Temple, Prem Mandir, Pagal Baba Temple',
                    ]],
                ],
                'inclusions' => ['AC private cab', 'Hotel stay (1 night)', 'Local guide', 'Darshan support'],
                'exclusions' => ['Meals not on itinerary', 'Temple donations'],
            ],
            [
                'title' => '2 Nights / 3 Days Tour',
                'duration_days' => 3,
                'duration_nights' => 2,
                'price' => 7499,
                'discounted_price' => 6499,
                'overview' => 'The complete Braj experience: Gokul, Mathura, Vrindavan, Agra highlights, plus Nandgaon, Barsana and Govardhan.',
                'category' => 'braj',
                'is_featured' => true,
                'day_wise_itinerary' => [
                    ['day' => 1, 'title' => 'Gokul & Mathura', 'points' => ['Gokul sightseeing', 'Mathura temple circuit']],
                    ['day' => 2, 'title' => 'Vrindavan Full Tour & Agra Highlights', 'points' => ['Full Vrindavan temple circuit', 'Agra highlights']],
                    ['day' => 3, 'title' => 'Nandgaon, Barsana & Govardhan', 'points' => [
                        'Nandgaon: Shree Nand Baba Temple, Asheshwar Mahadev',
                        'Barsana: Radha Rani Temple, Rang Ji Temple, Prem Sarovar, Kirti Mandir, Rangili Mahal',
                        'Govardhan: Govardhan Parvat, Kusum Sarovar, Danghati Temple, Mansi Ganga, Haridev Temple',
                    ]],
                ],
                'inclusions' => ['AC private cab', 'Hotel stay (2 nights)', 'Local guide', 'Darshan support'],
                'exclusions' => ['Meals not on itinerary', 'Temple donations', 'VIP darshan (optional add-on)'],
            ],
        ];

        foreach ($packages as $pkg) {
            TourPackage::firstOrCreate(
                ['slug' => Str::slug($pkg['title'])],
                $pkg + ['city_id' => $vrindavan->id, 'is_active' => true]
            );
        }
    }
}
