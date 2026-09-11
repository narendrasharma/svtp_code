<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the current VrindavanMathura.com tour catalogue into SVTP.
 *
 * Design decisions:
 * - Existing SVTP tours are NEVER updated or deleted.
 * - Exact slug/title matches are skipped.
 * - One known semantic duplicate is skipped when the existing
 *   "4 Nights 5 Days Complete Braj Darshan Tour" is present.
 * - cover_image is NULL and gallery is [] so images can be uploaded later
 *   from the SVTP admin panel.
 * - No CKEditor image placeholders are injected.
 * - Relations are attached only when the matching destination/place/tag
 *   already exists in the SVTP database.
 *
 * Source catalogue checked: 2026-09-10
 */
class VrindavanMathuraTourSeeder extends Seeder
{
    private array $commonInclusions = [
        'Private air-conditioned vehicle for sightseeing as per the itinerary',
        'Pickup and drop as mentioned in the confirmed itinerary',
        'Accommodation in a clean hotel or Braj ashram on twin-sharing basis',
        'Daily breakfast during the stay',
        'Experienced local driver',
        'Driver allowance, fuel, tolls and parking',
        'Local sightseeing as per the itinerary',
        'Trip coordination and darshan timing assistance',
    ];

    private array $commonExclusions = [
        'Lunch, dinner and personal expenses',
        'Temple donations and special or VIP darshan charges',
        'Monument or attraction entry tickets unless specifically included',
        'Boat rides or local e-rickshaw charges unless specifically included',
        'Hotel category upgrades',
        'GST or other taxes if applicable and not included in the final quote',
        'Anything not specifically mentioned under inclusions',
    ];

    public function run(): void
    {
        $this->assertRequiredReferenceData();

        $tours = $this->tours();

        $created = 0;
        $skipped = 0;

        foreach ($tours as $data) {
            DB::transaction(function () use ($data, &$created, &$skipped) {
                $existing = DB::table('tour_packages')
                    ->where('slug', $data['slug'])
                    ->orWhereRaw('LOWER(title) = ?', [mb_strtolower($data['title'])])
                    ->first();

                if ($existing) {
                    $this->command?->warn("SKIPPED exact match: {$data['title']} (existing ID {$existing->id})");
                    $skipped++;
                    return;
                }

                // Human-curated duplicate rule:
                // The existing SVTP package already covers the same complete 5-day Braj circuit.
                if (
                    $data['slug'] === '5-days-mathura-vrindavan-tour'
                    && DB::table('tour_packages')
                        ->where('slug', '4-nights-5-days-complete-braj-darshan-tour')
                        ->exists()
                ) {
                    $this->command?->warn(
                        "SKIPPED semantic duplicate: {$data['title']} -> existing 4 Nights 5 Days Complete Braj Darshan Tour"
                    );
                    $skipped++;
                    return;
                }

                $cityId = $this->cityId($data['city']);

                $tourId = DB::table('tour_packages')->insertGetId([
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'city_id' => $cityId,
                    'duration_days' => $data['days'],
                    'duration_nights' => $data['nights'],
                    'price' => $data['price'],
                    'discounted_price' => null,
                    'overview' => $this->overview($data),
                    'day_wise_itinerary' => json_encode($data['itinerary'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'inclusions' => json_encode($data['inclusions'] ?? $this->commonInclusions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'exclusions' => json_encode($data['exclusions'] ?? $this->commonExclusions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'gallery' => json_encode([], JSON_UNESCAPED_SLASHES),
                    'cover_image' => null,
                    'is_featured' => $data['featured'] ?? false,
                    'is_active' => true,
                    'category' => null,
                    'category_id' => $this->categoryId($data['category'] ?? 'Braj Darshan'),
                    'meta_description' => $data['meta_description'],
                    'meta_title' => $data['meta_title'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->syncNamedRelation(
                    'destination_tour_package',
                    'destination_id',
                    'destinations',
                    $tourId,
                    $data['destinations']
                );

                $this->syncNamedRelation(
                    'place_tour_package',
                    'place_id',
                    'places',
                    $tourId,
                    $data['places']
                );

                $this->syncNamedRelation(
                    'tag_tour_package',
                    'tag_id',
                    'tags',
                    $tourId,
                    $data['tags']
                );

                $this->command?->info("CREATED: {$data['title']} (ID {$tourId})");
                $created++;
            });
        }

        $this->command?->newLine();
        $this->command?->info("VrindavanMathura.com import complete.");
        $this->command?->info("Created: {$created}");
        $this->command?->info("Skipped: {$skipped}");
        $this->command?->info("Total tours now: " . DB::table('tour_packages')->count());
        $this->command?->comment('Cover images and gallery images were intentionally left empty for manual admin upload.');
    }

    private function overview(array $tour): string
    {
        $destinations = implode(', ', $tour['destinations']);

        $html = '<h2>' . e($tour['title']) . '</h2>';
        $html .= '<p>' . e($tour['intro']) . '</p>';
        $html .= '<p>This private journey is planned around comfortable transfers, practical darshan timings and the major spiritual or heritage highlights of ' . e($destinations) . '. The itinerary can be adjusted to suit family groups, senior travellers and different arrival points.</p>';

        foreach ($tour['itinerary'] as $day) {
            $html .= '<h3>Day ' . (int) $day['day'] . ' - ' . e($day['title']) . '</h3>';

            if (!empty($day['details'])) {
                $html .= '<ul>';
                foreach ($day['details'] as $detail) {
                    $html .= '<li>' . e($detail) . '</li>';
                }
                $html .= '</ul>';
            }
        }

        $html .= '<p><strong>Note:</strong> Temple timings, aarti schedules, traffic conditions and local restrictions can affect the sequence of sightseeing. The final itinerary can be adjusted accordingly.</p>';

        return $html;
    }

    private function syncNamedRelation(
        string $pivotTable,
        string $foreignKey,
        string $lookupTable,
        int $tourId,
        array $names
    ): void {
        if (empty($names)) {
            return;
        }

        $ids = DB::table($lookupTable)
            ->whereIn('name', $names)
            ->pluck('id')
            ->all();

        foreach ($ids as $id) {
            DB::table($pivotTable)->insertOrIgnore([
                $foreignKey => $id,
                'tour_package_id' => $tourId,
            ]);
        }
    }

    private function cityId(string $name): int
    {
        $id = DB::table('cities')->where('name', $name)->value('id');

        if (!$id) {
            throw new \RuntimeException("Required city not found: {$name}");
        }

        return (int) $id;
    }

    private function categoryId(string $name): ?int
    {
        return DB::table('tour_categories')->where('name', $name)->value('id');
    }

    private function assertRequiredReferenceData(): void
    {
        foreach (['Mathura', 'Vrindavan', 'Agra', 'Delhi'] as $city) {
            if (!DB::table('cities')->where('name', $city)->exists()) {
                throw new \RuntimeException("Missing required city: {$city}");
            }
        }

        if (!DB::table('tour_categories')->where('name', 'Braj Darshan')->exists()) {
            throw new \RuntimeException('Missing required tour category: Braj Darshan');
        }
    }

    private function tours(): array
    {
        $mathura = [
            'Shri Krishna Janmabhoomi',
            'Dwarkadhish Temple',
            'Vishram Ghat',
        ];

        $vrindavan = [
            'Banke Bihari Temple',
            'ISKCON Vrindavan',
            'Prem Mandir',
            'Radha Raman Temple',
            'Radha Vallabh Temple',
            'Nidhivan',
        ];

        $govardhan = [
            'Daan Ghati Temple',
            'Kusum Sarovar',
            'Mansi Ganga',
        ];

        $gokul = [
            'Nand Bhavan',
            'Raman Reti',
            'Brahmand Ghat',
        ];

        $barsana = [
            'Radha Rani Temple',
            'Kirti Mandir',
            'Rangili Mahal',
        ];

        $nandgaon = [
            'Nand Bhavan Nandgaon',
        ];

        $agra = [
            'Taj Mahal',
            'Agra Fort',
            'Mehtab Bagh',
        ];

        $basic2 = [
            ['day' => 1, 'title' => 'Mathura Darshan', 'details' => [
                'Visit Shri Krishna Janmabhoomi and Dwarkadhish Temple.',
                'Spend time at Vishram Ghat and the Yamuna riverfront.',
                'Continue according to available temple and aarti timings.',
            ]],
            ['day' => 2, 'title' => 'Vrindavan Darshan', 'details' => [
                'Morning darshan at Banke Bihari Temple.',
                'Visit ISKCON Vrindavan, Radha Raman Temple, Radha Vallabh Temple and Nidhivan.',
                'End the day with Prem Mandir and evening sightseeing.',
            ]],
        ];

        $basic3 = array_merge($basic2, [[
            'day' => 3,
            'title' => 'Govardhan Darshan',
            'details' => [
                'Visit Daan Ghati and the Govardhan pilgrimage area.',
                'Visit Kusum Sarovar and Radha Kund area.',
                'Optional Govardhan parikrama depending on time and traveller preference.',
            ],
        ]]);

        $basic4 = array_merge($basic3, [[
            'day' => 4,
            'title' => 'Gokul, Nandgaon & Barsana',
            'details' => [
                'Explore Gokul and Krishna childhood sites.',
                'Visit Nand Bhavan at Nandgaon.',
                'Proceed to Barsana for Radha Rani Temple and nearby Braj sites.',
            ],
        ]]);

        $basic5 = array_merge($basic4, [[
            'day' => 5,
            'title' => 'Relaxed Braj Darshan & Departure',
            'details' => [
                'Keep additional time for quieter temples, local darshan or missed sites.',
                'Complete the journey with a comfortable departure transfer.',
            ],
        ]]);

        $commonBrajDestinations2 = ['Mathura', 'Vrindavan'];
        $commonBrajDestinations3 = ['Mathura', 'Vrindavan', 'Govardhan'];
        $commonBrajDestinations4 = ['Mathura', 'Vrindavan', 'Govardhan', 'Gokul', 'Nandgaon', 'Barsana'];

        $commonTags = ['Braj', 'Pilgrimage', 'Family', 'Temple Tour', 'Multi Day'];

        return [
            [
                'title' => '2 Days Mathura Vrindavan Tour',
                'slug' => '2-days-mathura-vrindavan-tour',
                'city' => 'Mathura', 'days' => 2, 'nights' => 1, 'price' => 3999,
                'category' => 'Braj Darshan', 'featured' => true,
                'destinations' => $commonBrajDestinations2,
                'places' => array_merge($mathura, $vrindavan),
                'tags' => $commonTags,
                'itinerary' => $basic2,
                'intro' => 'A compact two-day Braj pilgrimage covering the principal temples and ghats of Mathura and Vrindavan at a comfortable pace.',
                'meta_title' => '2 Days Mathura Vrindavan Tour Package | SVTP',
                'meta_description' => 'Book a 2 Days Mathura Vrindavan tour with private sightseeing, temple darshan, hotel stay and major Braj attractions from ₹3,999 per person.',
            ],
            [
                'title' => '3 Days Mathura Vrindavan Tour & Itinerary',
                'slug' => '3-days-mathura-vrindavan-tour-package-itinerary',
                'city' => 'Mathura', 'days' => 3, 'nights' => 2, 'price' => 6999,
                'category' => 'Braj Darshan', 'featured' => true,
                'destinations' => $commonBrajDestinations3,
                'places' => array_merge($mathura, $vrindavan, $govardhan),
                'tags' => $commonTags,
                'itinerary' => $basic3,
                'intro' => 'A three-day Braj tour combining Mathura, Vrindavan and Govardhan for travellers who want the main Krishna pilgrimage circuit without rushing.',
                'meta_title' => '3 Days Mathura Vrindavan Tour Itinerary | SVTP',
                'meta_description' => 'Explore Mathura, Vrindavan and Govardhan in 3 days with private transport, accommodation and a planned Braj darshan itinerary from ₹6,999.',
            ],
            [
                'title' => '4 Days Mathura Vrindavan Tour',
                'slug' => '4-days-mathura-vrindavan-tour',
                'city' => 'Mathura', 'days' => 4, 'nights' => 3, 'price' => 9999,
                'category' => 'Braj Darshan', 'featured' => true,
                'destinations' => $commonBrajDestinations4,
                'places' => array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon),
                'tags' => $commonTags,
                'itinerary' => $basic4,
                'intro' => 'A complete four-day Braj circuit covering Mathura, Vrindavan, Govardhan, Gokul, Nandgaon and Barsana.',
                'meta_title' => '4 Days Mathura Vrindavan Braj Tour | SVTP',
                'meta_description' => 'Book a 4-day complete Braj tour covering Mathura, Vrindavan, Govardhan, Gokul, Nandgaon and Barsana from ₹9,999.',
            ],
            [
                'title' => '4 Days Mathura Vrindavan Tour from Mathura',
                'slug' => 'mathura-vrindavan-tour-4-days',
                'city' => 'Mathura', 'days' => 4, 'nights' => 3, 'price' => 9999,
                'category' => 'Braj Darshan',
                'destinations' => $commonBrajDestinations4,
                'places' => array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon),
                'tags' => $commonTags,
                'itinerary' => $basic4,
                'intro' => 'A four-day Braj pilgrimage designed for guests who begin their journey directly from Mathura.',
                'meta_title' => '4 Days Mathura Vrindavan Tour from Mathura | SVTP',
                'meta_description' => 'Start your 4-day Braj tour from Mathura and visit Vrindavan, Govardhan, Gokul, Nandgaon and Barsana from ₹9,999.',
            ],
            [
                'title' => '5 Days Mathura Vrindavan Tour',
                'slug' => '5-days-mathura-vrindavan-tour',
                'city' => 'Mathura', 'days' => 5, 'nights' => 4, 'price' => 11999,
                'category' => 'Braj Darshan', 'featured' => true,
                'destinations' => $commonBrajDestinations4,
                'places' => array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon),
                'tags' => $commonTags,
                'itinerary' => $basic5,
                'intro' => 'A relaxed five-day Braj experience with extra time for the major temples, ghats, sacred villages and Govardhan pilgrimage area.',
                'meta_title' => '5 Days Mathura Vrindavan Complete Braj Tour | SVTP',
                'meta_description' => 'Enjoy a relaxed 5-day complete Braj tour covering Mathura, Vrindavan, Govardhan, Gokul, Barsana and Nandgaon from ₹11,999.',
            ],
            [
                'title' => 'Agra Mathura Vrindavan Gokul Nandgaon Barsana Tour',
                'slug' => 'agra-mathura-vrindavan-gokul-nandgaon-barsana-tour-package',
                'city' => 'Agra', 'days' => 5, 'nights' => 4, 'price' => 9699,
                'category' => 'Heritage Tours', 'featured' => true,
                'destinations' => ['Agra', 'Mathura', 'Vrindavan', 'Govardhan', 'Gokul', 'Nandgaon', 'Barsana'],
                'places' => array_merge($agra, $mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon),
                'tags' => array_merge($commonTags, ['Agra', 'Heritage']),
                'itinerary' => [
                    ['day' => 1, 'title' => 'Agra Heritage', 'details' => ['Visit the Taj Mahal, Agra Fort and selected Agra heritage viewpoints.', 'Continue towards Mathura or Vrindavan for the Braj leg.']],
                    ['day' => 2, 'title' => 'Mathura Darshan', 'details' => ['Visit Shri Krishna Janmabhoomi, Dwarkadhish Temple and Vishram Ghat.']],
                    ['day' => 3, 'title' => 'Vrindavan Darshan', 'details' => ['Visit Banke Bihari Temple, ISKCON Vrindavan, Nidhivan, Radha Raman and Prem Mandir.']],
                    ['day' => 4, 'title' => 'Govardhan Darshan', 'details' => ['Visit Daan Ghati, Kusum Sarovar and the Govardhan pilgrimage area.']],
                    ['day' => 5, 'title' => 'Gokul, Nandgaon & Barsana', 'details' => ['Explore Gokul, Nandgaon and Barsana before departure.']],
                ],
                'intro' => 'A combined Taj Mahal and Braj pilgrimage journey that links Agra heritage with Mathura, Vrindavan and the sacred villages of Braj.',
                'meta_title' => 'Agra Mathura Vrindavan Gokul Barsana Tour | SVTP',
                'meta_description' => 'Combine Agra and complete Braj sightseeing with Mathura, Vrindavan, Gokul, Govardhan, Nandgaon and Barsana from ₹9,699.',
            ],
            [
                'title' => 'Agra Mathura Vrindavan Gokul Nandgaon & Barsana Tour',
                'slug' => 'agra-mathura-vrindavan-gokul-nandgaon-and-barsana-tour-package',
                'city' => 'Agra', 'days' => 4, 'nights' => 3, 'price' => 9699,
                'category' => 'Heritage Tours',
                'destinations' => ['Agra', 'Mathura', 'Vrindavan', 'Gokul', 'Nandgaon', 'Barsana'],
                'places' => array_merge($agra, $mathura, $vrindavan, $gokul, $barsana, $nandgaon),
                'tags' => array_merge($commonTags, ['Agra', 'Heritage']),
                'itinerary' => [
                    ['day' => 1, 'title' => 'Agra & Transfer to Braj', 'details' => ['Visit selected Agra monuments and continue to the Braj region.']],
                    ['day' => 2, 'title' => 'Mathura Darshan', 'details' => ['Shri Krishna Janmabhoomi, Dwarkadhish Temple and Vishram Ghat.']],
                    ['day' => 3, 'title' => 'Vrindavan Darshan', 'details' => ['Banke Bihari, ISKCON Vrindavan, Nidhivan and Prem Mandir.']],
                    ['day' => 4, 'title' => 'Gokul, Nandgaon & Barsana', 'details' => ['Visit Krishna childhood sites in Gokul, Nand Bhavan at Nandgaon and Radha Rani Temple at Barsana.']],
                ],
                'intro' => 'A four-day private journey combining Agra with Mathura, Vrindavan, Gokul, Nandgaon and Barsana.',
                'meta_title' => 'Agra Mathura Vrindavan Gokul Nandgaon Barsana | SVTP',
                'meta_description' => 'Explore Agra and the Braj circuit including Mathura, Vrindavan, Gokul, Nandgaon and Barsana from ₹9,699.',
            ],
            [
                'title' => 'Mathura Vrindavan Agra Tour Packages',
                'slug' => 'mathura-vrindavan-agra-tour-packages',
                'city' => 'Mathura', 'days' => 3, 'nights' => 2, 'price' => 7999,
                'category' => 'Heritage Tours',
                'destinations' => ['Mathura', 'Vrindavan', 'Agra'],
                'places' => array_merge($mathura, $vrindavan, $agra),
                'tags' => array_merge($commonTags, ['Agra', 'Heritage']),
                'itinerary' => [
                    ['day' => 1, 'title' => 'Mathura Darshan', 'details' => ['Visit Shri Krishna Janmabhoomi, Dwarkadhish Temple and Vishram Ghat.']],
                    ['day' => 2, 'title' => 'Vrindavan Darshan', 'details' => ['Visit Banke Bihari, ISKCON, Radha Raman, Nidhivan and Prem Mandir.']],
                    ['day' => 3, 'title' => 'Agra Heritage & Departure', 'details' => ['Visit the Taj Mahal, Agra Fort and selected heritage attractions.']],
                ],
                'intro' => 'A three-day combination of the Krishna pilgrimage towns of Mathura and Vrindavan with the Mughal heritage of Agra.',
                'meta_title' => 'Mathura Vrindavan Agra Tour Package | SVTP',
                'meta_description' => 'Visit Mathura, Vrindavan and Agra in one private 3-day trip with temple darshan and Taj Mahal sightseeing from ₹7,999.',
            ],

            // Delhi start variants
            $this->startVariant('Mathura Vrindavan Tour from Delhi (1 Night / 2 Days)', 'mathura-vrindavan-tour-package-from-delhi-1-nights-2-days', 'Delhi', 2, 1, 7999, $basic2, $commonBrajDestinations2, array_merge($mathura, $vrindavan), $commonTags),
            $this->startVariant('Mathura Vrindavan Tour from Delhi (2 Nights / 3 Days)', 'mathura-vrindavan-tour-package-from-delhi-2-nights-3-days', 'Delhi', 3, 2, 7999, $basic3, $commonBrajDestinations3, array_merge($mathura, $vrindavan, $govardhan), $commonTags),
            $this->startVariant('Mathura Vrindavan Tour from Delhi (3 Nights / 4 Days)', 'mathura-vrindavan-tour-package-from-delhi-3-nights-4-days', 'Delhi', 4, 3, 11999, $basic4, $commonBrajDestinations4, array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon), $commonTags),

            // Agra start variants
            $this->startVariant('Mathura Vrindavan Tour from Agra (1 Night / 2 Days)', 'mathura-vrindavan-tour-package-from-agra-1-night-2-days', 'Agra', 2, 1, 6999, $basic2, $commonBrajDestinations2, array_merge($mathura, $vrindavan), array_merge($commonTags, ['Agra'])),
            $this->startVariant('Mathura Vrindavan Tour from Agra (2 Nights / 3 Days)', 'mathura-vrindavan-tour-package-from-agra-2-nights-3-days', 'Agra', 3, 2, 6999, $basic3, $commonBrajDestinations3, array_merge($mathura, $vrindavan, $govardhan), array_merge($commonTags, ['Agra'])),
            $this->startVariant('Mathura Vrindavan Tour from Agra (3 Nights / 4 Days)', 'mathura-vrindavan-tour-package-from-agra-3-nights-4-days', 'Agra', 4, 3, 10999, $basic4, $commonBrajDestinations4, array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon), array_merge($commonTags, ['Agra'])),

            // Mathura start variants
            $this->startVariant('Mathura Vrindavan Tour from Mathura (1 Night / 2 Days)', 'mathura-vrindavan-tour-package-from-mathura-1-night-2-days', 'Mathura', 2, 1, 4999, $basic2, $commonBrajDestinations2, array_merge($mathura, $vrindavan), $commonTags),
            $this->startVariant('Mathura Vrindavan Tour Starting from Mathura Junction (2 Nights / 3 Days)', 'mathura-vrindavan-tour-package-from-mathura-2-nights-3-days', 'Mathura', 3, 2, 6499, $basic3, $commonBrajDestinations3, array_merge($mathura, $vrindavan, $govardhan), $commonTags),
            $this->startVariant('Mathura Vrindavan Tour from Mathura (3 Nights / 4 Days)', 'mathura-vrindavan-tour-package-from-mathura-3-nights-4-days', 'Mathura', 4, 3, 9999, $basic4, $commonBrajDestinations4, array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon), $commonTags),

            [
                'title' => 'Mathura and Vrindavan Tour',
                'slug' => 'mathura-and-vrindavan-tour',
                'city' => 'Mathura', 'days' => 2, 'nights' => 1, 'price' => 3999,
                'category' => 'Braj Darshan',
                'destinations' => ['Mathura', 'Vrindavan', 'Gokul'],
                'places' => array_merge($mathura, $vrindavan, $gokul),
                'tags' => $commonTags,
                'itinerary' => [
                    ['day' => 1, 'title' => 'Mathura & Gokul', 'details' => ['Visit Shri Krishna Janmabhoomi, Vishram Ghat and Dwarkadhish Temple.', 'Continue to Gokul for Nand Bhavan, Raman Reti and nearby Krishna childhood sites.']],
                    ['day' => 2, 'title' => 'Vrindavan', 'details' => ['Visit Banke Bihari, ISKCON Vrindavan, Prem Mandir, Radha Raman and Nidhivan.']],
                ],
                'intro' => 'A flexible Mathura, Gokul and Vrindavan pilgrimage designed as a short private Braj tour with temple sightseeing and local transfers.',
                'meta_title' => 'Mathura and Vrindavan Tour Package | SVTP',
                'meta_description' => 'Plan a private Mathura and Vrindavan tour with Gokul sightseeing, temple darshan and comfortable transfers from ₹3,999.',
            ],

            // Flexible start variants
            $this->flexibleVariant('2 Days Mathura Vrindavan Tour from Delhi / Agra / Mathura', '2-days-mathura-vrindavan-tour-package-from-delhi-agra-mathura', 2, 1, 3999, $basic2, $commonBrajDestinations2, array_merge($mathura, $vrindavan), $commonTags),
            $this->flexibleVariant('3 Days Mathura Vrindavan Tour from Delhi / Agra / Mathura', '3-days-mathura-vrindavan-tour-from-delhi-agra-mathura', 3, 2, 6999, $basic3, $commonBrajDestinations3, array_merge($mathura, $vrindavan, $govardhan), $commonTags),

            $this->startVariant('4 Days Mathura Vrindavan Tour from Delhi', '4-days-mathura-vrindavan-tour-from-delhi', 'Delhi', 4, 3, 11999, $basic4, $commonBrajDestinations4, array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon), $commonTags),

            $this->flexibleVariant('4 Days Mathura Vrindavan Tour from Delhi / Agra / Mathura', '4-days-mathura-vrindavan-tour-from-delhi-agra-mathura', 4, 3, 9999, $basic4, $commonBrajDestinations4, array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon), $commonTags),
            $this->flexibleVariant('5 Days Mathura Vrindavan Tour from Delhi / Agra / Mathura', '5-days-mathura-vrindavan-tour-delhi-agra-mathura', 5, 4, 11999, $basic5, $commonBrajDestinations4, array_merge($mathura, $vrindavan, $govardhan, $gokul, $barsana, $nandgaon), $commonTags),
        ];
    }

    private function startVariant(
        string $title,
        string $slug,
        string $startCity,
        int $days,
        int $nights,
        int $price,
        array $itinerary,
        array $destinations,
        array $places,
        array $tags
    ): array {
        $itinerary[0]['details'] = array_merge([
            "Pickup from {$startCity} and comfortable transfer to the Braj region.",
        ], $itinerary[0]['details'] ?? []);

        return [
            'title' => $title,
            'slug' => $slug,
            'city' => $startCity,
            'days' => $days,
            'nights' => $nights,
            'price' => $price,
            'category' => 'Braj Darshan',
            'destinations' => $destinations,
            'places' => $places,
            'tags' => $tags,
            'itinerary' => $itinerary,
            'intro' => "A private {$days}-day Braj pilgrimage starting from {$startCity}, with pickup, sightseeing and temple visits planned around the main Mathura-Vrindavan circuit.",
            'meta_title' => Str::limit($title . ' | SVTP', 60, ''),
            'meta_description' => Str::limit("Book {$title} with private transport, hotel stay and planned Braj sightseeing from ₹" . number_format($price) . ' per person.', 155, ''),
        ];
    }

    private function flexibleVariant(
        string $title,
        string $slug,
        int $days,
        int $nights,
        int $price,
        array $itinerary,
        array $destinations,
        array $places,
        array $tags
    ): array {
        $itinerary[0]['details'] = array_merge([
            'Pickup can be arranged from Delhi, Agra or Mathura according to the confirmed booking.',
        ], $itinerary[0]['details'] ?? []);

        return [
            'title' => $title,
            'slug' => $slug,
            'city' => 'Mathura',
            'days' => $days,
            'nights' => $nights,
            'price' => $price,
            'category' => 'Braj Darshan',
            'destinations' => $destinations,
            'places' => $places,
            'tags' => $tags,
            'itinerary' => $itinerary,
            'intro' => "A flexible {$days}-day private Braj tour that can begin from Delhi, Agra or Mathura and covers the major pilgrimage destinations according to the selected duration.",
            'meta_title' => Str::limit($title . ' | SVTP', 60, ''),
            'meta_description' => Str::limit("Plan {$title} with private sightseeing and flexible pickup from ₹" . number_format($price) . ' per person.', 155, ''),
        ];
    }
}
