<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TourPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogDemoSeeder extends Seeder
{
    public const TOUR_SLUGS = [
        'same-day-darshan-tour', '1-night-2-days-tour', '2-nights-3-days-tour',
        'vrindavan-temple-trail', 'mathura-krishna-janmabhoomi-tour', 'govardhan-parikrama-day-tour',
        'barsana-radha-rani-darshan', 'braj-weekend-pilgrimage', 'senior-friendly-vrindavan-yatra',
        'private-family-braj-tour', 'same-day-agra-heritage-tour', 'agra-fort-and-taj-mahal-day-tour',
        'delhi-to-mathura-vrindavan-day-tour', 'delhi-agra-mathura-golden-triangle',
        'braj-holi-festival-tour', 'janmashtami-mathura-special',
        'vrindavan-and-govardhan-two-day-tour', 'agra-and-vrindavan-weekend-tour',
    ];

    public function run(): void
    {
        $this->call(StateCitySeeder::class);

        DB::transaction(function (): void {
            $destinations = $this->seedDestinations();
            $places = $this->seedPlaces($destinations);
            $tags = $this->seedTags();

            foreach ($this->tourRecords() as $tourData) {
                $destinationSlugs = $tourData['destinations'];
                $placeSlugs = $tourData['places'];
                $tagSlugs = $tourData['tags'];
                $city = City::where('slug', $tourData['city'])->firstOrFail();
                unset($tourData['destinations'], $tourData['places'], $tourData['tags'], $tourData['city']);

                $tour = TourPackage::updateOrCreate(
                    ['slug' => $tourData['slug']],
                    $tourData + ['city_id' => $city->id, 'is_active' => true]
                );
                $tour->destinations()->sync($destinations->only($destinationSlugs)->pluck('id')->all());
                $tour->places()->sync($places->only($placeSlugs)->pluck('id')->all());
                $tour->tags()->sync($tags->only($tagSlugs)->pluck('id')->all());
            }
        });

        Cache::forget('home.featured_packages');
        Cache::forget('home.destinations.autocomplete');
    }

    /** @return Collection<string, Destination> */
    private function seedDestinations(): Collection
    {
        $records = [
            'vrindavan' => ['Vrindavan', 'vrindavan', 'The sacred town of Radha and Krishna, known for historic temples, devotional lanes and Yamuna ghats.'],
            'mathura' => ['Mathura', 'mathura', 'The birthplace of Lord Krishna, with Janmabhoomi, ancient temples and evening aarti at Vishram Ghat.'],
            'govardhan' => ['Govardhan', 'govardhan', 'A major Braj pilgrimage destination centred on Govardhan Hill, parikrama and sacred kunds.'],
            'barsana' => ['Barsana', 'barsana', 'Radha Rani’s birthplace, celebrated for hilltop temples, Braj culture and Lathmar Holi.'],
            'agra' => ['Agra', 'agra', 'A Mughal heritage destination featuring the Taj Mahal, Agra Fort and riverside monuments.'],
            'delhi' => ['Delhi', 'delhi', 'India’s capital and a convenient gateway for Braj and Agra tours.'],
        ];

        return collect($records)->mapWithKeys(function (array $data, string $slug): array {
            $city = City::where('slug', $data[1])->firstOrFail();
            $destination = Destination::firstOrCreate(['slug' => $slug], [
                'city_id' => $city->id, 'name' => $data[0], 'description' => $data[2], 'meta_description' => $data[2],
            ]);

            return [$slug => $destination];
        });
    }

    /**
     * @param  Collection<string, Destination>  $destinations
     * @return Collection<string, Place>
     */
    private function seedPlaces(Collection $destinations): Collection
    {
        $records = [
            'prem-mandir' => ['Prem Mandir', 'vrindavan', 'A luminous marble temple dedicated to Radha Krishna and Sita Ram.'],
            'banke-bihari-temple' => ['Banke Bihari Temple', 'vrindavan', 'Vrindavan’s beloved temple known for intimate darshan of Banke Bihari Ji.'],
            'iskcon-vrindavan' => ['ISKCON Vrindavan', 'vrindavan', 'The Krishna Balaram Mandir, known for kirtan and international devotees.'],
            'nidhivan' => ['Nidhivan', 'vrindavan', 'A sacred grove associated with the divine pastimes of Radha and Krishna.'],
            'krishna-janmabhoomi' => ['Shri Krishna Janmabhoomi', 'mathura', 'The revered birthplace complex of Lord Krishna in Mathura.'],
            'dwarkadhish-temple' => ['Dwarkadhish Temple', 'mathura', 'A historic Mathura temple with richly decorated seasonal celebrations.'],
            'vishram-ghat' => ['Vishram Ghat', 'mathura', 'Mathura’s principal Yamuna ghat and setting for evening aarti.'],
            'govardhan-hill' => ['Govardhan Hill', 'govardhan', 'The sacred hill traditionally walked as a devotional parikrama.'],
            'kusum-sarovar' => ['Kusum Sarovar', 'govardhan', 'A sandstone reservoir and heritage monument on the Govardhan route.'],
            'mansi-ganga' => ['Mansi Ganga', 'govardhan', 'A sacred lake beside the lively heart of Govardhan town.'],
            'radha-rani-temple' => ['Radha Rani Temple', 'barsana', 'The hilltop Shri Laadli Lal temple overlooking Barsana.'],
            'kirti-mandir' => ['Kirti Mandir', 'barsana', 'A modern temple honouring Radha Rani and her mother Kirti.'],
            'prem-sarovar' => ['Prem Sarovar', 'barsana', 'A tranquil sacred pond between Barsana and Nandgaon.'],
            'taj-mahal' => ['Taj Mahal', 'agra', 'The world-famous white marble mausoleum beside the Yamuna River.'],
            'agra-fort' => ['Agra Fort', 'agra', 'A UNESCO-listed Mughal fort with palaces and Taj Mahal views.'],
            'mehtab-bagh' => ['Mehtab Bagh', 'agra', 'A riverside Mughal garden offering sunset views of the Taj Mahal.'],
            'india-gate' => ['India Gate', 'delhi', 'New Delhi’s landmark war memorial on the ceremonial avenue.'],
            'akshardham-temple' => ['Akshardham Temple', 'delhi', 'A monumental temple complex showcasing Indian spirituality and craftsmanship.'],
            'lotus-temple' => ['Lotus Temple', 'delhi', 'A lotus-shaped house of worship known for serene architecture.'],
        ];

        return collect($records)->mapWithKeys(function (array $data, string $slug) use ($destinations): array {
            $place = Place::firstOrCreate(['slug' => $slug], [
                'destination_id' => $destinations[$data[1]]->id,
                'name' => $data[0], 'description' => $data[2], 'meta_description' => $data[2],
            ]);

            return [$slug => $place];
        });
    }

    /** @return Collection<string, Tag> */
    private function seedTags(): Collection
    {
        $records = [
            'family' => 'Family', 'senior-friendly' => 'Senior Friendly', 'pilgrimage' => 'Pilgrimage',
            'same-day' => 'Same Day', 'weekend' => 'Weekend', 'heritage' => 'Heritage',
            'festival' => 'Festival', 'private-tour' => 'Private Tour',
        ];

        return collect($records)->mapWithKeys(fn (string $name, string $slug): array => [
            $slug => Tag::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function tourRecords(): array
    {
        return [
            $this->tour('Same-Day Darshan Tour', 'same-day-darshan-tour', 'vrindavan', 1, 2499, 'A focused same-day pilgrimage through Mathura and Vrindavan’s essential Krishna temples.', 'braj', ['mathura', 'vrindavan'], ['krishna-janmabhoomi', 'banke-bihari-temple', 'prem-mandir'], ['same-day', 'pilgrimage', 'family'], true),
            $this->tour('1 Night / 2 Days Tour', '1-night-2-days-tour', 'vrindavan', 2, 4999, 'A relaxed Braj break with Mathura heritage and an unhurried Vrindavan temple circuit.', 'braj', ['mathura', 'vrindavan'], ['krishna-janmabhoomi', 'vishram-ghat', 'banke-bihari-temple', 'prem-mandir'], ['weekend', 'pilgrimage', 'family'], true),
            $this->tour('2 Nights / 3 Days Tour', '2-nights-3-days-tour', 'vrindavan', 3, 7499, 'A complete Braj journey covering Mathura, Vrindavan, Govardhan and Barsana.', 'braj', ['mathura', 'vrindavan', 'govardhan', 'barsana'], ['krishna-janmabhoomi', 'prem-mandir', 'govardhan-hill', 'radha-rani-temple'], ['pilgrimage', 'family', 'private-tour'], true),
            $this->tour('Vrindavan Temple Trail', 'vrindavan-temple-trail', 'vrindavan', 1, 2199, 'A guided temple trail through Vrindavan with local assistance and practical darshan timing.', 'temple', ['vrindavan'], ['banke-bihari-temple', 'iskcon-vrindavan', 'prem-mandir', 'nidhivan'], ['pilgrimage', 'same-day'], true),
            $this->tour('Mathura Krishna Janmabhoomi Tour', 'mathura-krishna-janmabhoomi-tour', 'mathura', 1, 1999, 'Discover Krishna’s birthplace, Dwarkadhish Temple and Yamuna aarti in historic Mathura.', 'temple', ['mathura'], ['krishna-janmabhoomi', 'dwarkadhish-temple', 'vishram-ghat'], ['pilgrimage', 'same-day', 'heritage']),
            $this->tour('Govardhan Parikrama Day Tour', 'govardhan-parikrama-day-tour', 'govardhan', 1, 2299, 'A supported Govardhan parikrama with stops at Mansi Ganga and Kusum Sarovar.', 'temple', ['govardhan'], ['govardhan-hill', 'mansi-ganga', 'kusum-sarovar'], ['pilgrimage', 'same-day', 'private-tour']),
            $this->tour('Barsana Radha Rani Darshan', 'barsana-radha-rani-darshan', 'barsana', 1, 2399, 'Visit Radha Rani Temple, Kirti Mandir and peaceful Prem Sarovar with a Braj guide.', 'temple', ['barsana'], ['radha-rani-temple', 'kirti-mandir', 'prem-sarovar'], ['pilgrimage', 'same-day', 'family']),
            $this->tour('Braj Weekend Pilgrimage', 'braj-weekend-pilgrimage', 'vrindavan', 2, 5799, 'A weekend pilgrimage combining Vrindavan, Mathura, Govardhan and Barsana.', 'braj', ['vrindavan', 'mathura', 'govardhan', 'barsana'], ['prem-mandir', 'krishna-janmabhoomi', 'govardhan-hill', 'radha-rani-temple'], ['weekend', 'pilgrimage', 'family'], true),
            $this->tour('Senior Friendly Vrindavan Yatra', 'senior-friendly-vrindavan-yatra', 'vrindavan', 2, 6499, 'A slower-paced Vrindavan and Mathura itinerary with shorter walks and rest breaks.', 'braj', ['vrindavan', 'mathura'], ['prem-mandir', 'iskcon-vrindavan', 'krishna-janmabhoomi'], ['senior-friendly', 'pilgrimage', 'private-tour'], true),
            $this->tour('Private Family Braj Tour', 'private-family-braj-tour', 'vrindavan', 3, 10999, 'A flexible private Braj itinerary designed for families travelling with children or elders.', 'braj', ['vrindavan', 'mathura', 'govardhan'], ['banke-bihari-temple', 'prem-mandir', 'krishna-janmabhoomi', 'kusum-sarovar'], ['family', 'private-tour', 'senior-friendly']),
            $this->tour('Same Day Agra Heritage Tour', 'same-day-agra-heritage-tour', 'agra', 1, 3499, 'A same-day private visit to the Taj Mahal, Agra Fort and Mehtab Bagh.', 'up_circuit', ['agra'], ['taj-mahal', 'agra-fort', 'mehtab-bagh'], ['same-day', 'heritage', 'private-tour'], true),
            $this->tour('Agra Fort and Taj Mahal Day Tour', 'agra-fort-and-taj-mahal-day-tour', 'agra', 1, 3999, 'A guided Mughal heritage day featuring two UNESCO World Heritage monuments.', 'up_circuit', ['agra'], ['taj-mahal', 'agra-fort'], ['heritage', 'same-day', 'family']),
            $this->tour('Delhi to Mathura Vrindavan Day Tour', 'delhi-to-mathura-vrindavan-day-tour', 'delhi', 1, 5999, 'Early departure from Delhi for Krishna Janmabhoomi and Vrindavan’s major temples.', 'up_circuit', ['delhi', 'mathura', 'vrindavan'], ['krishna-janmabhoomi', 'banke-bihari-temple', 'prem-mandir'], ['same-day', 'private-tour', 'pilgrimage'], true),
            $this->tour('Delhi Agra Mathura Golden Triangle', 'delhi-agra-mathura-golden-triangle', 'delhi', 3, 13999, 'A private circuit linking Delhi landmarks, Mughal Agra and sacred Mathura.', 'up_circuit', ['delhi', 'agra', 'mathura'], ['india-gate', 'taj-mahal', 'agra-fort', 'krishna-janmabhoomi'], ['heritage', 'private-tour', 'family'], true),
            $this->tour('Braj Holi Festival Tour', 'braj-holi-festival-tour', 'barsana', 4, 15999, 'Experience Lathmar Holi and temple celebrations across Barsana, Mathura and Vrindavan.', 'festival', ['barsana', 'mathura', 'vrindavan'], ['radha-rani-temple', 'krishna-janmabhoomi', 'banke-bihari-temple'], ['festival', 'pilgrimage', 'family'], true),
            $this->tour('Janmashtami Mathura Special', 'janmashtami-mathura-special', 'mathura', 2, 8999, 'Celebrate Janmashtami with guided arrangements around Mathura and Vrindavan.', 'festival', ['mathura', 'vrindavan'], ['krishna-janmabhoomi', 'dwarkadhish-temple', 'iskcon-vrindavan'], ['festival', 'pilgrimage', 'weekend'], true),
            $this->tour('Vrindavan and Govardhan Two Day Tour', 'vrindavan-and-govardhan-two-day-tour', 'vrindavan', 2, 6299, 'Combine Vrindavan darshan with Govardhan’s sacred parikrama sites.', 'braj', ['vrindavan', 'govardhan'], ['banke-bihari-temple', 'prem-mandir', 'govardhan-hill', 'kusum-sarovar'], ['weekend', 'pilgrimage', 'private-tour']),
            $this->tour('Agra and Vrindavan Weekend Tour', 'agra-and-vrindavan-weekend-tour', 'agra', 2, 8499, 'A balanced weekend combining Taj Mahal heritage with Vrindavan temple darshan.', 'up_circuit', ['agra', 'vrindavan'], ['taj-mahal', 'agra-fort', 'banke-bihari-temple', 'prem-mandir'], ['weekend', 'heritage', 'pilgrimage'], true),
        ];
    }

    /** @return array<string, mixed> */
    private function tour(string $title, string $slug, string $city, int $days, int $price, string $overview,
        string $category, array $destinations, array $places, array $tags, bool $featured = false): array
    {
        return [
            'title' => $title, 'slug' => $slug, 'city' => $city,
            'duration_days' => $days, 'duration_nights' => max(0, $days - 1),
            'price' => $price, 'discounted_price' => $price - 500,
            'overview' => $overview, 'category' => $category, 'is_featured' => $featured,
            'day_wise_itinerary' => [['day' => 1, 'title' => $title, 'points' => ['Private pickup and sightseeing', 'Guided attraction visits']]],
            'inclusions' => ['Private AC vehicle', 'Local tour assistance', 'Pickup and drop'],
            'exclusions' => ['Meals', 'Monument entry fees', 'Personal expenses'],
            'meta_description' => $overview, 'destinations' => $destinations, 'places' => $places, 'tags' => $tags,
        ];
    }
}
