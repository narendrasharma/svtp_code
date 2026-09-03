<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenuineTourCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            /*
             * This seeder is idempotent: it updates existing records by slug/name
             * and syncs pivots. It does NOT wipe users, bookings, enquiries, states,
             * cities, or authentication data.
             *
             * Prices and images are intentionally not invented. price = 0.00 and
             * gallery/cover_image are empty so you can update them from Admin later.
             */

            $stateIds = $this->seedStates($now);
            $cityIds = $this->seedCities($stateIds, $now);
            $categoryIds = $this->seedCategories($now);
            $tagIds = $this->seedTags($now);
            $destinationIds = $this->seedDestinations($cityIds, $now);
            $placeIds = $this->seedPlaces($destinationIds, $now);

            $defaultInclusions = [
                'Private sightseeing vehicle as selected for the confirmed package',
                'Pickup and drop as agreed in the final itinerary',
                'Driver allowance, fuel and local sightseeing transport',
                'Trip coordination and itinerary assistance',
            ];

            $defaultExclusions = [
                'Hotel accommodation unless specifically included in the confirmed quote',
                'Meals and beverages unless specifically mentioned',
                'Monument, temple, parking, toll or special-darshan charges unless specifically included',
                'Guide charges unless specifically included',
                'Personal expenses, shopping and optional activities',
            ];

            $tours = [
                [
                    'title' => 'Same Day Gokul, Mathura & Vrindavan Tour',
                    'category' => 'braj-darshan',
                    'city' => 'mathura',
                    'days' => 1,
                    'nights' => 0,
                    'featured' => true,
                    'overview' => 'A compact one-day Braj journey covering the childhood land of Shri Krishna in Gokul, the sacred temples and ghats of Mathura, and the devotional atmosphere of Vrindavan. Ideal for travelers who want to experience the key Krishna pilgrimage sites in a single day.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Gokul - Mathura - Vrindavan',
                            'details' => [
                                'Begin with Gokul and visit Nand Bhavan, Raman Reti, Brahmand Ghat, Chintaharan Mahadev and Chaurasi Khamba.',
                                'Continue to Mathura for Shri Krishna Janmabhoomi, Dwarkadhish Temple and Vishram Ghat.',
                                'Proceed to Vrindavan for Banke Bihari Temple, Radha Vallabh Temple, Madan Mohan Temple, Nidhivan, ISKCON and Prem Mandir.',
                                'Return after the evening visit, subject to local temple timings.',
                            ],
                        ],
                    ],
                    'destinations' => ['gokul', 'mathura', 'vrindavan'],
                    'places' => [
                        'nand-bhavan', 'raman-reti', 'brahmand-ghat', 'chintaharan-mahadev',
                        'chaurasi-khamba', 'shri-krishna-janmabhoomi', 'dwarkadhish-temple',
                        'vishram-ghat', 'banke-bihari-temple', 'radha-vallabh-temple',
                        'madan-mohan-temple', 'nidhivan', 'iskcon-vrindavan', 'prem-mandir',
                    ],
                    'tags' => ['same-day', 'braj', 'pilgrimage', 'family', 'temple-tour'],
                ],
                [
                    'title' => '1 Night 2 Days Gokul, Mathura & Vrindavan Tour',
                    'category' => 'braj-darshan',
                    'city' => 'mathura',
                    'days' => 2,
                    'nights' => 1,
                    'featured' => true,
                    'overview' => 'A relaxed two-day pilgrimage designed for travelers who want more time for darshan in Gokul, Mathura and Vrindavan. The first day focuses on Gokul and Mathura, while the second day is dedicated to the major temples and sacred sites of Vrindavan.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Gokul & Mathura',
                            'details' => [
                                'Visit Nand Bhavan, Raman Reti, Brahmand Ghat, Chintaharan Mahadev and Chaurasi Khamba in Gokul.',
                                'Continue to Mathura for Shri Krishna Janmabhoomi, Dwarkadhish Temple and Vishram Ghat.',
                                'Evening at leisure or Yamuna Aarti when available.',
                            ],
                        ],
                        [
                            'day' => 2,
                            'title' => 'Vrindavan Full-Day Darshan',
                            'details' => [
                                'Visit Banke Bihari Temple, Radha Vallabh Temple, Radha Raman Temple and Madan Mohan Temple.',
                                'Continue to Nidhivan, ISKCON and other major Vrindavan temples as time allows.',
                                'Conclude with Prem Mandir in the evening, subject to opening and light-show timings.',
                            ],
                        ],
                    ],
                    'destinations' => ['gokul', 'mathura', 'vrindavan'],
                    'places' => [
                        'nand-bhavan', 'raman-reti', 'brahmand-ghat', 'chintaharan-mahadev',
                        'chaurasi-khamba', 'shri-krishna-janmabhoomi', 'dwarkadhish-temple',
                        'vishram-ghat', 'banke-bihari-temple', 'radha-vallabh-temple',
                        'radha-raman-temple', 'madan-mohan-temple', 'nidhivan',
                        'iskcon-vrindavan', 'prem-mandir',
                    ],
                    'tags' => ['braj', 'pilgrimage', 'family', 'temple-tour', 'two-days'],
                ],
                [
                    'title' => '2 Nights 3 Days Complete Braj Darshan Tour',
                    'category' => 'braj-darshan',
                    'city' => 'mathura',
                    'days' => 3,
                    'nights' => 2,
                    'featured' => true,
                    'overview' => 'A three-day Braj itinerary combining Gokul, Mathura and Vrindavan with the sacred villages and pilgrimage spots of Nandgaon, Barsana and Govardhan. This package is suited to devotees who want a broader Braj experience without rushing every visit into one day.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Gokul & Mathura',
                            'details' => [
                                'Gokul: Nand Bhavan, Raman Reti, Brahmand Ghat, Chintaharan Mahadev and Chaurasi Khamba.',
                                'Mathura: Shri Krishna Janmabhoomi, Dwarkadhish Temple and Vishram Ghat.',
                            ],
                        ],
                        [
                            'day' => 2,
                            'title' => 'Vrindavan',
                            'details' => [
                                'Full-day temple visits including Banke Bihari, Radha Vallabh, Radha Raman, Madan Mohan, Nidhivan, ISKCON and Prem Mandir.',
                            ],
                        ],
                        [
                            'day' => 3,
                            'title' => 'Nandgaon - Barsana - Govardhan',
                            'details' => [
                                'Nandgaon: Nand Bhavan and nearby Krishna-associated sites.',
                                'Barsana: Shri Radha Rani Temple, Kirti Mandir, Maan Mandir and Rangili Mahal.',
                                'Govardhan: Daan Ghati, Mansi Ganga, Kusum Sarovar and selected sacred parikrama-area sites.',
                            ],
                        ],
                    ],
                    'destinations' => ['gokul', 'mathura', 'vrindavan', 'nandgaon', 'barsana', 'govardhan'],
                    'places' => [
                        'nand-bhavan', 'raman-reti', 'brahmand-ghat', 'shri-krishna-janmabhoomi',
                        'dwarkadhish-temple', 'vishram-ghat', 'banke-bihari-temple',
                        'radha-vallabh-temple', 'radha-raman-temple', 'madan-mohan-temple',
                        'nidhivan', 'iskcon-vrindavan', 'prem-mandir', 'radha-rani-temple',
                        'kirti-mandir', 'maan-mandir', 'rangili-mahal', 'daan-ghati-temple',
                        'mansi-ganga', 'kusum-sarovar',
                    ],
                    'tags' => ['braj', 'pilgrimage', 'family', 'temple-tour', 'multi-day'],
                ],
                [
                    'title' => 'Govardhan, Barsana & Nandgaon Braj Tour',
                    'category' => 'braj-darshan',
                    'city' => 'mathura',
                    'days' => 1,
                    'nights' => 0,
                    'featured' => false,
                    'overview' => 'Explore three deeply devotional Braj destinations in one carefully planned day. Visit Govardhan for Krishna-linked pilgrimage sites, Barsana for Radha Rani traditions and hilltop temples, and Nandgaon for places associated with Krishna’s childhood with Nanda and Yashoda.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Govardhan - Barsana - Nandgaon',
                            'details' => [
                                'Start early at Govardhan with Daan Ghati, Mansi Ganga and selected parikrama/sacred kund points.',
                                'Continue to Barsana for Shri Radha Rani Temple, Maan Mandir and nearby devotional sites.',
                                'Proceed to Nandgaon for Nand Bhavan and other Krishna childhood landmarks.',
                                'Return to Mathura/Vrindavan by evening.',
                            ],
                        ],
                    ],
                    'destinations' => ['govardhan', 'barsana', 'nandgaon'],
                    'places' => ['daan-ghati-temple', 'mansi-ganga', 'kusum-sarovar', 'radha-rani-temple', 'maan-mandir', 'rangili-mahal', 'nand-bhavan-nandgaon'],
                    'tags' => ['same-day', 'braj', 'pilgrimage', 'family', 'temple-tour'],
                ],
                [
                    'title' => 'Same Day Agra Heritage Tour',
                    'category' => 'heritage-tours',
                    'city' => 'agra',
                    'days' => 1,
                    'nights' => 0,
                    'featured' => true,
                    'overview' => 'Discover Agra’s signature Mughal landmarks in a comfortable one-day sightseeing plan. The tour combines the Taj Mahal and Agra Fort with additional heritage stops such as Mehtab Bagh and Itmad-ud-Daulah, depending on available time.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Agra Sightseeing',
                            'details' => [
                                'Visit the Taj Mahal, preferably during the cooler morning hours.',
                                'Explore Agra Fort and its major palace sections.',
                                'Continue to Itmad-ud-Daulah (Baby Taj).',
                                'Visit Mehtab Bagh for a riverside Taj Mahal view if time permits.',
                                'Return to pickup point after sightseeing.',
                            ],
                        ],
                    ],
                    'destinations' => ['agra'],
                    'places' => ['taj-mahal', 'agra-fort', 'itmad-ud-daulah', 'mehtab-bagh'],
                    'tags' => ['same-day', 'heritage', 'family', 'agra'],
                ],
                [
                    'title' => 'Agra & Fatehpur Sikri Same Day Tour',
                    'category' => 'heritage-tours',
                    'city' => 'agra',
                    'days' => 1,
                    'nights' => 0,
                    'featured' => false,
                    'overview' => 'A full-day heritage experience covering the Mughal monuments of Agra and the historic complex of Fatehpur Sikri. See the Taj Mahal and Agra Fort before continuing to Akbar’s former imperial capital with its gateways, courtyards, palaces and sacred spaces.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Agra - Fatehpur Sikri',
                            'details' => [
                                'Morning Taj Mahal visit followed by Agra Fort.',
                                'Optional stop at Mehtab Bagh or Itmad-ud-Daulah depending on pace.',
                                'Drive to Fatehpur Sikri.',
                                'Explore Buland Darwaza, Jama Masjid, Salim Chishti Tomb, Panch Mahal and Diwan-i-Khas.',
                                'Return to Agra/Mathura/Vrindavan after sightseeing.',
                            ],
                        ],
                    ],
                    'destinations' => ['agra', 'fatehpur-sikri'],
                    'places' => [
                        'taj-mahal', 'agra-fort', 'mehtab-bagh', 'itmad-ud-daulah',
                        'buland-darwaza', 'jama-masjid-fatehpur-sikri', 'salim-chishti-tomb',
                        'panch-mahal', 'diwan-i-khas-fatehpur-sikri',
                    ],
                    'tags' => ['same-day', 'heritage', 'family', 'agra'],
                ],
                [
                    'title' => 'Jaipur, Khatu Shyam & Mehandipur Balaji Tour',
                    'category' => 'rajasthan-pilgrimage',
                    'city' => 'jaipur',
                    'days' => 3,
                    'nights' => 2,
                    'featured' => false,
                    'overview' => 'A Rajasthan pilgrimage and sightseeing journey combining Jaipur’s famous temples with darshan at Khatu Shyam Ji and Mehandipur Balaji. The plan balances devotional visits with selected Jaipur attractions and can be customized around temple timings.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Jaipur Temple & City Tour',
                            'details' => [
                                'Visit Govind Dev Ji Temple, Birla Mandir and Moti Dungri Ganesh Temple.',
                                'Continue to Galta Ji and selected Jaipur sightseeing points depending on time.',
                            ],
                        ],
                        [
                            'day' => 2,
                            'title' => 'Khatu Shyam Ji',
                            'details' => [
                                'Early departure from Jaipur for Khatu Shyam Ji.',
                                'Darshan at the main temple and visit Shyam Kund when practical.',
                                'Return toward Jaipur after darshan.',
                            ],
                        ],
                        [
                            'day' => 3,
                            'title' => 'Mehandipur Balaji',
                            'details' => [
                                'Depart for Mehandipur Balaji.',
                                'Darshan at Balaji Temple according to local customs and permitted access.',
                                'Return toward Jaipur or onward destination.',
                            ],
                        ],
                    ],
                    'destinations' => ['jaipur', 'khatu-shyam', 'mehandipur-balaji'],
                    'places' => [
                        'govind-dev-ji-temple', 'birla-mandir-jaipur', 'moti-dungri-ganesh-temple',
                        'galta-ji', 'khatu-shyam-ji-temple', 'mehandipur-balaji-temple',
                    ],
                    'tags' => ['pilgrimage', 'family', 'temple-tour', 'rajasthan', 'multi-day'],
                ],
                [
                    'title' => 'Ayodhya & Varanasi Spiritual Tour',
                    'category' => 'up-spiritual-circuit',
                    'city' => 'ayodhya',
                    'days' => 3,
                    'nights' => 2,
                    'featured' => false,
                    'overview' => 'A devotional journey through Ayodhya and Varanasi, combining Shri Ram pilgrimage sites with the ghats and Shiva temples of Kashi. The itinerary includes major darshan points, riverfront experiences and time for local spiritual exploration.',
                    'itinerary' => [
                        [
                            'day' => 1,
                            'title' => 'Ayodhya Darshan',
                            'details' => [
                                'Visit Shri Ram Janmabhoomi Temple and Hanuman Garhi.',
                                'Continue to Kanak Bhawan and Nageshwarnath Temple.',
                                'Visit Saryu River and Ram Ki Paidi; attend evening aarti when available.',
                            ],
                        ],
                        [
                            'day' => 2,
                            'title' => 'Varanasi Temples & Ghats',
                            'details' => [
                                'Sunrise visit around Assi Ghat.',
                                'Darshan at Kashi Vishwanath Temple.',
                                'Explore important local temples and ghats.',
                                'Attend evening Ganga Aarti at Dashashwamedh Ghat and take an optional boat ride.',
                            ],
                        ],
                        [
                            'day' => 3,
                            'title' => 'Sarnath & Varanasi',
                            'details' => [
                                'Visit Sarnath and its main Buddhist heritage sites.',
                                'Optional local temple visits and shopping before departure from Varanasi.',
                            ],
                        ],
                    ],
                    'destinations' => ['ayodhya', 'varanasi'],
                    'places' => [
                        'ram-janmabhoomi-temple', 'hanuman-garhi-ayodhya', 'kanak-bhawan',
                        'ram-ki-paidi', 'nageshwarnath-temple', 'kashi-vishwanath-temple',
                        'dashashwamedh-ghat', 'assi-ghat', 'sarnath', 'manikarnika-ghat',
                    ],
                    'tags' => ['pilgrimage', 'family', 'temple-tour', 'up-circuit', 'multi-day'],
                ],
            ];

            foreach ($tours as $data) {
                $slug = Str::slug($data['title']);

                DB::table('tour_packages')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'title' => $data['title'],
                        'city_id' => $cityIds[$data['city']],
                        'duration_days' => $data['days'],
                        'duration_nights' => $data['nights'],
                        'price' => 0.00,
                        'discounted_price' => null,
                        'overview' => $data['overview'],
                        'day_wise_itinerary' => json_encode($data['itinerary'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'inclusions' => json_encode($defaultInclusions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'exclusions' => json_encode($defaultExclusions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'gallery' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'cover_image' => null,
                        'is_featured' => $data['featured'],
                        'is_active' => true,
                        'category' => null, // legacy column kept only for compatibility
                        'category_id' => $categoryIds[$data['category']],
                        'meta_description' => Str::limit($data['overview'], 155, ''),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $tourId = (int) DB::table('tour_packages')->where('slug', $slug)->value('id');

                $this->syncPivot(
                    'destination_tour_package',
                    'destination_id',
                    $tourId,
                    array_map(fn ($s) => $destinationIds[$s], $data['destinations'])
                );

                $this->syncPivot(
                    'place_tour_package',
                    'place_id',
                    $tourId,
                    array_map(fn ($s) => $placeIds[$s], $data['places'])
                );

                $this->syncPivot(
                    'tag_tour_package',
                    'tag_id',
                    $tourId,
                    array_map(fn ($s) => $tagIds[$s], $data['tags'])
                );
            }
        });
    }

    private function seedStates($now): array
    {
        $rows = [
            'uttar-pradesh' => 'Uttar Pradesh',
            'rajasthan' => 'Rajasthan',
        ];

        $ids = [];
        foreach ($rows as $slug => $name) {
            DB::table('states')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'updated_at' => $now, 'created_at' => $now]
            );
            $ids[$slug] = (int) DB::table('states')->where('slug', $slug)->value('id');
        }

        return $ids;
    }

    private function seedCities(array $stateIds, $now): array
    {
        $rows = [
            ['name' => 'Mathura', 'slug' => 'mathura', 'state' => 'uttar-pradesh', 'hub' => true],
            ['name' => 'Agra', 'slug' => 'agra', 'state' => 'uttar-pradesh', 'hub' => false],
            ['name' => 'Ayodhya', 'slug' => 'ayodhya', 'state' => 'uttar-pradesh', 'hub' => true],
            ['name' => 'Varanasi', 'slug' => 'varanasi', 'state' => 'uttar-pradesh', 'hub' => true],
            ['name' => 'Jaipur', 'slug' => 'jaipur', 'state' => 'rajasthan', 'hub' => false],
            ['name' => 'Sikar', 'slug' => 'sikar', 'state' => 'rajasthan', 'hub' => false],
            ['name' => 'Dausa', 'slug' => 'dausa', 'state' => 'rajasthan', 'hub' => false],
        ];

        $ids = [];
        foreach ($rows as $row) {
            DB::table('cities')->updateOrInsert(
                ['slug' => $row['slug']],
                [
                    'state_id' => $stateIds[$row['state']],
                    'name' => $row['name'],
                    'is_spiritual_hub' => $row['hub'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $ids[$row['slug']] = (int) DB::table('cities')->where('slug', $row['slug'])->value('id');
        }

        return $ids;
    }

    private function seedCategories($now): array
    {
        $rows = [
            ['name' => 'Braj Darshan', 'slug' => 'braj-darshan', 'description' => 'Tours covering Mathura, Vrindavan and the wider Braj region.'],
            ['name' => 'Heritage Tours', 'slug' => 'heritage-tours', 'description' => 'Heritage and monument-focused sightseeing tours.'],
            ['name' => 'Rajasthan Pilgrimage', 'slug' => 'rajasthan-pilgrimage', 'description' => 'Temple and pilgrimage journeys across selected Rajasthan destinations.'],
            ['name' => 'UP Spiritual Circuit', 'slug' => 'up-spiritual-circuit', 'description' => 'Spiritual journeys connecting major pilgrimage destinations in Uttar Pradesh.'],
        ];

        $ids = [];
        foreach ($rows as $i => $row) {
            DB::table('tour_categories')->updateOrInsert(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'icon' => null,
                    'description' => $row['description'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $ids[$row['slug']] = (int) DB::table('tour_categories')->where('slug', $row['slug'])->value('id');
        }

        return $ids;
    }

    private function seedTags($now): array
    {
        $names = [
            'Same Day', 'Braj', 'Pilgrimage', 'Family', 'Temple Tour',
            'Two Days', 'Multi Day', 'Heritage', 'Agra', 'Rajasthan', 'UP Circuit',
        ];

        $ids = [];
        foreach ($names as $name) {
            $slug = Str::slug($name);
            DB::table('tags')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
            $ids[$slug] = (int) DB::table('tags')->where('slug', $slug)->value('id');
        }

        return $ids;
    }

    private function seedDestinations(array $cityIds, $now): array
    {
        $rows = [
            ['Gokul', 'gokul', 'mathura', 'Explore Gokul, the Braj town associated with Shri Krishna’s childhood and important devotional sites.'],
            ['Mathura', 'mathura', 'mathura', 'Discover Mathura, the sacred birthplace of Shri Krishna, with temples, ghats and historic pilgrimage sites.'],
            ['Vrindavan', 'vrindavan', 'mathura', 'Experience Vrindavan through its celebrated Krishna temples, devotional lanes, sacred groves and evening darshan.'],
            ['Govardhan', 'govardhan', 'mathura', 'Visit Govardhan and its parikrama landscape, sacred kunds and Krishna-associated pilgrimage places.'],
            ['Barsana', 'barsana', 'mathura', 'Explore Barsana, the traditional home of Shri Radha, known for Radha Rani Temple and Braj devotional culture.'],
            ['Nandgaon', 'nandgaon', 'mathura', 'Visit Nandgaon, associated with Nanda Baba and the childhood years of Shri Krishna.'],
            ['Agra', 'agra', 'agra', 'Explore Agra’s Mughal heritage, including the Taj Mahal, Agra Fort and riverside monuments.'],
            ['Fatehpur Sikri', 'fatehpur-sikri', 'agra', 'Discover the historic Mughal city of Fatehpur Sikri with monumental gateways, courtyards and palaces.'],
            ['Jaipur', 'jaipur', 'jaipur', 'Discover Jaipur through temple visits, heritage landmarks and Rajasthan culture.'],
            ['Khatu Shyam', 'khatu-shyam', 'sikar', 'Plan darshan at the revered Khatu Shyam Ji Temple and nearby devotional sites.'],
            ['Mehandipur Balaji', 'mehandipur-balaji', 'dausa', 'Visit the well-known Mehandipur Balaji pilgrimage destination in Rajasthan.'],
            ['Ayodhya', 'ayodhya', 'ayodhya', 'Explore Ayodhya through Shri Ram pilgrimage sites, temples and the sacred Saryu riverfront.'],
            ['Varanasi', 'varanasi', 'varanasi', 'Experience Kashi through sacred ghats, Kashi Vishwanath Temple, Ganga Aarti and nearby heritage sites.'],
        ];

        $ids = [];
        foreach ($rows as [$name, $slug, $citySlug, $description]) {
            DB::table('destinations')->updateOrInsert(
                ['slug' => $slug],
                [
                    'city_id' => $cityIds[$citySlug],
                    'name' => $name,
                    'description' => $description,
                    'image' => null,
                    'meta_description' => Str::limit($description, 155, ''),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $ids[$slug] = (int) DB::table('destinations')->where('slug', $slug)->value('id');
        }

        return $ids;
    }

    private function seedPlaces(array $destinationIds, $now): array
    {
        $rows = [
            ['Nand Bhavan', 'nand-bhavan', 'gokul'],
            ['Raman Reti', 'raman-reti', 'gokul'],
            ['Brahmand Ghat', 'brahmand-ghat', 'gokul'],
            ['Chintaharan Mahadev', 'chintaharan-mahadev', 'gokul'],
            ['Chaurasi Khamba', 'chaurasi-khamba', 'gokul'],

            ['Shri Krishna Janmabhoomi', 'shri-krishna-janmabhoomi', 'mathura'],
            ['Dwarkadhish Temple', 'dwarkadhish-temple', 'mathura'],
            ['Vishram Ghat', 'vishram-ghat', 'mathura'],

            ['Banke Bihari Temple', 'banke-bihari-temple', 'vrindavan'],
            ['Radha Vallabh Temple', 'radha-vallabh-temple', 'vrindavan'],
            ['Radha Raman Temple', 'radha-raman-temple', 'vrindavan'],
            ['Madan Mohan Temple', 'madan-mohan-temple', 'vrindavan'],
            ['Nidhivan', 'nidhivan', 'vrindavan'],
            ['ISKCON Vrindavan', 'iskcon-vrindavan', 'vrindavan'],
            ['Prem Mandir', 'prem-mandir', 'vrindavan'],

            ['Radha Rani Temple', 'radha-rani-temple', 'barsana'],
            ['Kirti Mandir', 'kirti-mandir', 'barsana'],
            ['Maan Mandir', 'maan-mandir', 'barsana'],
            ['Rangili Mahal', 'rangili-mahal', 'barsana'],

            ['Daan Ghati Temple', 'daan-ghati-temple', 'govardhan'],
            ['Mansi Ganga', 'mansi-ganga', 'govardhan'],
            ['Kusum Sarovar', 'kusum-sarovar', 'govardhan'],
            ['Nand Bhavan Nandgaon', 'nand-bhavan-nandgaon', 'nandgaon'],

            ['Taj Mahal', 'taj-mahal', 'agra'],
            ['Agra Fort', 'agra-fort', 'agra'],
            ['Itmad-ud-Daulah', 'itmad-ud-daulah', 'agra'],
            ['Mehtab Bagh', 'mehtab-bagh', 'agra'],

            ['Buland Darwaza', 'buland-darwaza', 'fatehpur-sikri'],
            ['Jama Masjid Fatehpur Sikri', 'jama-masjid-fatehpur-sikri', 'fatehpur-sikri'],
            ['Salim Chishti Tomb', 'salim-chishti-tomb', 'fatehpur-sikri'],
            ['Panch Mahal', 'panch-mahal', 'fatehpur-sikri'],
            ['Diwan-i-Khas Fatehpur Sikri', 'diwan-i-khas-fatehpur-sikri', 'fatehpur-sikri'],

            ['Govind Dev Ji Temple', 'govind-dev-ji-temple', 'jaipur'],
            ['Birla Mandir Jaipur', 'birla-mandir-jaipur', 'jaipur'],
            ['Moti Dungri Ganesh Temple', 'moti-dungri-ganesh-temple', 'jaipur'],
            ['Galta Ji', 'galta-ji', 'jaipur'],
            ['Khatu Shyam Ji Temple', 'khatu-shyam-ji-temple', 'khatu-shyam'],
            ['Mehandipur Balaji Temple', 'mehandipur-balaji-temple', 'mehandipur-balaji'],

            ['Ram Janmabhoomi Temple', 'ram-janmabhoomi-temple', 'ayodhya'],
            ['Hanuman Garhi Ayodhya', 'hanuman-garhi-ayodhya', 'ayodhya'],
            ['Kanak Bhawan', 'kanak-bhawan', 'ayodhya'],
            ['Ram Ki Paidi', 'ram-ki-paidi', 'ayodhya'],
            ['Nageshwarnath Temple', 'nageshwarnath-temple', 'ayodhya'],

            ['Kashi Vishwanath Temple', 'kashi-vishwanath-temple', 'varanasi'],
            ['Dashashwamedh Ghat', 'dashashwamedh-ghat', 'varanasi'],
            ['Assi Ghat', 'assi-ghat', 'varanasi'],
            ['Sarnath', 'sarnath', 'varanasi'],
            ['Manikarnika Ghat', 'manikarnika-ghat', 'varanasi'],
        ];

        $ids = [];
        foreach ($rows as [$name, $slug, $destinationSlug]) {
            DB::table('places')->updateOrInsert(
                ['slug' => $slug],
                [
                    'destination_id' => $destinationIds[$destinationSlug],
                    'name' => $name,
                    'description' => null,
                    'image' => null,
                    'meta_description' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $ids[$slug] = (int) DB::table('places')->where('slug', $slug)->value('id');
        }

        return $ids;
    }

    private function syncPivot(string $table, string $foreignKey, int $tourId, array $ids): void
    {
        DB::table($table)->where('tour_package_id', $tourId)->delete();

        $rows = collect(array_unique($ids))
            ->map(fn ($id) => [
                $foreignKey => $id,
                'tour_package_id' => $tourId,
            ])
            ->all();

        if ($rows) {
            DB::table($table)->insert($rows);
        }
    }
}
