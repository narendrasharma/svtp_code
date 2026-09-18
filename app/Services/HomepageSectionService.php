<?php

namespace App\Services;

use App\Models\HomepageSection;
use Illuminate\Support\Collection;

/**
 * Homepage section configuration.
 *
 * Section records configure presentation (visibility, order and a small set
 * of typed display settings). They never duplicate domain content, which
 * keeps coming from packages, banners, reviews and static theme assets.
 *
 * System-defined keys are the source of truth here: unknown keys stored in
 * the database are ignored, and missing rows fall back to these defaults so
 * the homepage can never render blank on a fresh or partial install.
 */
class HomepageSectionService
{
    /**
     * System-defined sections in default homepage order.
     *
     * Each definition holds admin-facing copy, the default presentation
     * settings, their validation rules and a schema describing which
     * controls the manager UI should render.
     *
     * @return array<string, array{label: string, description: string, defaults: array<string, mixed>, rules: array<string, mixed>, schema: array<string, mixed>}>
     */
    public static function definitions(): array
    {
        return [
            'hero' => [
                'label' => 'Hero Slider',
                'description' => 'Full-width banner slider with the destination search overlay.',
                'defaults' => ['show_search' => true],
                'rules' => ['show_search' => ['required', 'boolean']],
                'schema' => ['show_search' => true],
            ],
            'divider' => [
                'label' => 'Decorative Divider',
                'description' => 'Small ornamental divider rendered below the hero.',
                'defaults' => [],
                'rules' => [],
                'schema' => [],
            ],
            'circuits' => [
                'label' => 'Tour Circuit Chips',
                'description' => 'Quick links to the most popular tour circuits.',
                'defaults' => [],
                'rules' => [],
                'schema' => [],
            ],
            'shloka_ticker' => [
                'label' => 'Verse Ticker',
                'description' => 'Scrolling devotional verse strip.',
                'defaults' => [],
                'rules' => [],
                'schema' => [],
            ],
            'director_message' => [
                'label' => "Director's Message",
                'description' => 'Personal welcome note from the founder.',
                'defaults' => [],
                'rules' => [],
                'schema' => [],
            ],
            'featured_packages' => [
                'label' => 'Featured Tour Packages',
                'description' => 'Package cards with ratings and booking links.',
                'defaults' => ['heading' => 'Popular Tour Packages', 'max_items' => 6, 'source' => 'featured'],
                'rules' => [
                    'heading' => ['nullable', 'string', 'max:120'],
                    'max_items' => ['required', 'integer', 'min:1', 'max:12'],
                    'source' => ['required', 'string', 'in:featured,latest'],
                ],
                'schema' => ['heading' => true, 'max_items' => ['min' => 1, 'max' => 12], 'source' => ['featured', 'latest']],
            ],
            'why_choose_us' => [
                'label' => 'Why Choose Us',
                'description' => 'Trust highlights with icons.',
                'defaults' => ['heading' => 'Why Travel With Us?'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'services' => [
                'label' => 'Services',
                'description' => 'Taxi, hotel and guide service cards.',
                'defaults' => ['heading' => 'Everything You Need for Your Journey'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'trust_strip' => [
                'label' => 'Trust Statistics Strip',
                'description' => 'Counters for travellers guided, rating, pricing and support.',
                'defaults' => [],
                'rules' => [],
                'schema' => [],
            ],
            'attractions' => [
                'label' => 'Top Attractions',
                'description' => 'Must-visit places card grid linking to destinations.',
                'defaults' => ['heading' => 'Must-Visit Places'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'best_time' => [
                'label' => 'Best Time to Visit',
                'description' => 'Seasonal weather guide table.',
                'defaults' => ['heading' => 'Best Time to Visit'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'gallery' => [
                'label' => 'Photo Gallery Preview',
                'description' => 'Image grid linking to the full gallery.',
                'defaults' => ['heading' => 'Moments From Our Tours'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'testimonials' => [
                'label' => 'Guest Testimonials',
                'description' => 'Approved guest reviews with star ratings.',
                'defaults' => ['heading' => 'What Our Guests Say', 'max_items' => 9],
                'rules' => [
                    'heading' => ['nullable', 'string', 'max:120'],
                    'max_items' => ['required', 'integer', 'min:1', 'max:12'],
                ],
                'schema' => ['heading' => true, 'max_items' => ['min' => 1, 'max' => 12]],
            ],
            'blog' => [
                'label' => 'Travel Blog Preview',
                'description' => 'Latest travel guides and articles.',
                'defaults' => ['heading' => 'From the Travel Blog'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'faq' => [
                'label' => 'FAQs',
                'description' => 'Expandable frequently asked questions.',
                'defaults' => ['heading' => 'Frequently Asked Questions'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'contact' => [
                'label' => 'Contact Section',
                'description' => 'Contact details and head-office card.',
                'defaults' => ['heading' => 'Contact Our Travel Team'],
                'rules' => ['heading' => ['nullable', 'string', 'max:120']],
                'schema' => ['heading' => true],
            ],
            'trust_marquee' => [
                'label' => 'Trust Badge Marquee',
                'description' => 'Scrolling partner and payment badges.',
                'defaults' => [],
                'rules' => [],
                'schema' => [],
            ],
        ];
    }

    /**
     * Ordered section keys for a fresh install.
     *
     * @return array<int, string>
     */
    public static function defaultOrder(): array
    {
        return array_keys(static::definitions());
    }

    public static function isKnownKey(string $key): bool
    {
        return array_key_exists($key, static::definitions());
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $key): array
    {
        return static::definitions()[$key]['defaults'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $key): array
    {
        return static::definitions()[$key]['rules'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedSettingKeys(string $key): array
    {
        return array_keys(static::validationRules($key));
    }

    /**
     * Merge stored settings over the code defaults, keeping known keys only.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function mergeSettings(string $key, ?array $stored): array
    {
        $allowed = static::allowedSettingKeys($key);

        return array_merge(
            static::defaultSettings($key),
            array_intersect_key($stored ?? [], array_flip($allowed))
        );
    }

    /**
     * Insert missing section rows so every known key is manageable.
     * Idempotent; safe to call on every manager visit.
     */
    public function ensureStored(): void
    {
        $existing = HomepageSection::query()->pluck('section_key')->all();

        foreach (static::defaultOrder() as $position => $key) {
            if (in_array($key, $existing, true)) {
                continue;
            }
            HomepageSection::query()->create([
                'section_key' => $key,
                'is_active' => true,
                'sort_order' => $position,
                'settings' => null,
            ]);
        }
    }

    /**
     * All known sections with stored state overlaid, in manager order.
     * Unknown stored keys are ignored; missing rows fall back to defaults.
     *
     * @return Collection<int, array{id: int|null, key: string, label: string, description: string, is_active: bool, sort_order: int, settings: array<string, mixed>, defaults: array<string, mixed>, schema: array<string, mixed>}>
     */
    public function all(): Collection
    {
        $rows = HomepageSection::query()->get()->keyBy('section_key');

        return collect(static::definitions())
            ->map(function (array $definition, string $key) use ($rows): array {
                $row = $rows->get($key);

                return [
                    'id' => $row?->id,
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'is_active' => $row ? (bool) $row->is_active : true,
                    'sort_order' => $row ? (int) $row->sort_order : array_search($key, static::defaultOrder()),
                    'settings' => static::mergeSettings($key, $row?->settings),
                    'defaults' => $definition['defaults'],
                    'schema' => $definition['schema'],
                ];
            })
            ->sortBy([['sort_order', 'asc'], ['key', 'asc']])
            ->values();
    }

    /**
     * Active sections for public rendering, in display order.
     * Falls back to the full default set so the homepage never renders blank.
     *
     * @return Collection<int, array{key: string, settings: array<string, mixed>}>
     */
    public function forHomepage(): Collection
    {
        $active = $this->all()->where('is_active', true)->values();

        if ($active->isEmpty()) {
            return collect(static::defaultOrder())->map(fn (string $key): array => [
                'key' => $key,
                'settings' => static::defaultSettings($key),
            ]);
        }

        return $active->map(fn (array $section): array => [
            'key' => $section['key'],
            'settings' => $section['settings'],
        ]);
    }
}
