<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Models\City;
use App\Models\Currency;
use App\Models\Destination;
use App\Models\ExchangeRate;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\HotelRatePlan;
use App\Models\HotelReview;
use App\Models\HotelRoomType;
use App\Models\Place;
use App\Models\Property;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\CurrencyConversionService;
use App\Services\HomepageSectionService;
use App\Services\HomepageService;
use App\Support\CurrencyRegistry;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\HomepageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function section(string $key): HomepageSection
    {
        return HomepageSection::query()->where('section_key', $key)->firstOrFail();
    }

    /** @return array<int, array{id: int, sort_order: int}> */
    protected function fullOrderPayload(): array
    {
        return HomepageSection::query()->whereIn('section_key', HomepageSectionService::defaultOrder())
            ->orderBy('id')
            ->get()
            ->map(fn (HomepageSection $section, int $index): array => ['id' => $section->id, 'sort_order' => $index])
            ->all();
    }

    public function test_admin_can_open_the_section_manager_with_seeded_sections(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.homepage-sections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/HomepageSections/Index')
                ->has('sections', count(HomepageSectionService::defaultOrder()))
                ->where('sections.0.key', 'hero')
            );
    }

    public function test_admin_can_toggle_section_visibility_without_losing_settings(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('testimonials');
        $section->update(['settings' => ['heading' => 'Guest Love', 'max_items' => 4]]);

        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('homepage_sections', [
            'id' => $section->id,
            'is_active' => false,
            'settings' => json_encode(['heading' => 'Guest Love', 'max_items' => 4]),
        ]);

        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('homepage_sections', ['id' => $section->id, 'is_active' => true]);
    }

    public function test_admin_can_persist_section_order(): void
    {
        $this->actingAsAdmin();
        $ids = HomepageSection::query()->whereIn('section_key', HomepageSectionService::defaultOrder())
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $payload = collect(array_reverse($ids))
            ->map(fn (int $id, int $position): array => ['id' => $id, 'sort_order' => $position])
            ->values()
            ->all();

        $this->put(route('admin.homepage-sections.reorder'), ['sections' => $payload])
            ->assertRedirect();

        foreach ($payload as $entry) {
            $this->assertDatabaseHas('homepage_sections', ['id' => $entry['id'], 'sort_order' => $entry['sort_order']]);
        }
        $this->assertSame(
            array_reverse($ids),
            HomepageSection::query()->whereIn('section_key', HomepageSectionService::defaultOrder())->orderBy('sort_order')->pluck('id')->all()
        );
    }

    public function test_invalid_reorder_payloads_are_rejected_without_changes(): void
    {
        $this->actingAsAdmin();
        $before = HomepageSection::query()->pluck('sort_order', 'id')->all();
        $valid = $this->fullOrderPayload();

        $invalid = [
            ['sections' => array_slice($valid, 1)], // missing an id
            ['sections' => array_merge(array_slice($valid, 1), [['id' => 999999, 'sort_order' => 0]])], // foreign id
            ['sections' => array_map(fn ($entry) => ['id' => $entry['id'], 'sort_order' => 0], $valid)], // duplicate positions
            ['sections' => []], // empty set
            ['sections' => 'not-an-array'],
        ];

        foreach ($invalid as $payload) {
            $this->putJson(route('admin.homepage-sections.reorder'), $payload)->assertUnprocessable();
            $this->assertSame($before, HomepageSection::query()->pluck('sort_order', 'id')->all());
        }
    }

    public function test_section_settings_are_validated(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('featured_packages');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['heading' => str_repeat('a', 121), 'max_items' => 6, 'source' => 'featured'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.heading');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['heading' => 'Tours', 'max_items' => 13, 'source' => 'featured'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.max_items');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['heading' => 'Tours', 'max_items' => 6, 'source' => 'random'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.source');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['custom_html' => '<script>alert(1)</script>'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.custom_html');

        $this->put(route('admin.homepage-sections.update', $section), [
            'is_active' => true,
            'settings' => ['heading' => 'Handpicked Tours', 'max_items' => 3, 'source' => 'latest'],
        ])->assertRedirect();

        $section->refresh();
        $this->assertSame(['heading' => 'Handpicked Tours', 'max_items' => 3, 'source' => 'latest'], $section->settings);
    }

    public function test_save_and_reorder_carry_the_flash_contract(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('faq');

        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => false])
            ->assertRedirect();
        $this->get(route('admin.homepage-sections.index'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.message', 'Homepage section saved.'));

        $this->put(route('admin.homepage-sections.reorder'), ['sections' => $this->fullOrderPayload()])
            ->assertRedirect();
        $this->get(route('admin.homepage-sections.index'))
            ->assertInertia(fn (Assert $page) => $page->where('flash.message', 'Section order saved.'));
    }

    public function test_section_manager_requires_an_admin(): void
    {
        $section = $this->section('faq');

        $this->get(route('admin.homepage-sections.index'))->assertRedirect(route('login'));
        $this->put(route('admin.homepage-sections.update', $section), ['is_active' => false])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->get(route('admin.homepage-sections.index'))->assertForbidden();
        $this->put(route('admin.homepage-sections.reorder'), ['sections' => []])->assertForbidden();
    }

    public function test_public_composer_returns_supported_sections_in_stored_order_with_compact_items(): void
    {
        $city = City::factory()->featured()->create(['name' => 'Composer City', 'sort_order' => 1]);
        $destination = Destination::factory()->featured()->create(['city_id' => $city->id, 'name' => 'Composer Destination', 'sort_order' => 1]);
        Place::factory()->create(['destination_id' => $destination->id, 'name' => 'Composer Place', 'sort_order' => 1]);
        TourPackage::factory()->featured()->create(['city_id' => $city->id, 'title' => 'Composer Tour']);

        $payload = app(HomepageService::class)->compose();
        $types = array_column($payload['sections'], 'type');

        $this->assertSame([
            'hero_search',
            'featured_cities',
            'featured_destinations',
            'featured_tours',
            'featured_places',
            'custom_cta',
        ], $types);
        $destinationSection = collect($payload['sections'])->firstWhere('type', 'featured_destinations');
        $this->assertSame('Composer Destination', $destinationSection['items'][0]['name']);
        $this->assertArrayNotHasKey('description', $destinationSection['items'][0]);
    }

    public function test_manual_destination_items_keep_configuration_but_omit_inactive_entities_publicly(): void
    {
        $section = $this->section('featured_destinations');
        $active = Destination::factory()->create(['name' => 'Active Destination']);
        $inactive = Destination::factory()->inactive()->create(['name' => 'Inactive Destination']);
        $section->update(['source_mode' => 'manual', 'item_limit' => 2]);
        HomepageSectionItem::factory()->create(['homepage_section_id' => $section->id, 'entity_type' => 'destination', 'entity_id' => $inactive->id, 'sort_order' => 0]);
        HomepageSectionItem::factory()->create(['homepage_section_id' => $section->id, 'entity_type' => 'destination', 'entity_id' => $active->id, 'sort_order' => 1]);

        $items = collect(app(HomepageService::class)->compose()['sections'])
            ->firstWhere('type', 'featured_destinations')['items'];

        $this->assertSame([$active->id], array_column($items, 'id'));
        $this->assertSame('manual', $section->fresh()->source_mode);
        $this->assertSame(2, $section->fresh()->item_limit);
    }

    public function test_hotel_sections_are_module_gated_without_deleting_configuration(): void
    {
        config()->set('modules.hotels.available', true);
        Setting::setValue('modules.hotels.enabled', '1');
        $section = $this->section('featured_hotels');
        $section->update(['settings' => ['title' => 'Curated stays'], 'item_limit' => 1]);
        Property::factory()->create(['status' => 'published', 'is_featured' => true]);

        $enabledTypes = array_column(app(HomepageService::class)->compose()['sections'], 'type');
        $this->assertContains('featured_hotels', $enabledTypes);

        Setting::setValue('modules.hotels.enabled', '0');
        $disabledTypes = array_column(app(HomepageService::class)->compose()['sections'], 'type');
        $this->assertNotContains('featured_hotels', $disabledTypes);
        $this->assertSame('Curated stays', $section->fresh()->settings['title']);

        config()->set('modules.hotels.available', false);
    }

    public function test_localized_section_title_and_safe_hero_default_tab_are_resolved(): void
    {
        $hero = $this->section('hero_search');
        $hero->update(['settings' => ['title' => 'Find a journey', 'default_tab' => 'hotels']]);
        $hero->setTranslation('hi', 'title', 'अपनी यात्रा खोजें');

        $payload = app(HomepageService::class)->compose('hi');
        $heroPayload = collect($payload['sections'])->firstWhere('type', 'hero_search');

        $this->assertSame('अपनी यात्रा खोजें', $heroPayload['title']);
        $this->assertSame(['tours', 'taxi'], $heroPayload['configuration']['tabs']);
        $this->assertSame('tours', $heroPayload['configuration']['default_tab']);
    }

    public function test_merchandising_update_rejects_unknown_entity_types_and_unsafe_settings(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('featured_destinations');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'source_mode' => 'manual',
            'items' => [['entity_type' => 'users', 'entity_id' => 1, 'sort_order' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.entity_type');

        $this->putJson(route('admin.homepage-sections.update', $section), [
            'settings' => ['custom_html' => '<script>alert(1)</script>'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.custom_html');

        $this->assertFalse(HomepageSectionService::isSupportedType('App\\Models\\User'));
        $this->assertSame([], HomepageSectionService::manualEntityModels()['users'] ?? []);
    }

    public function test_merchandising_reorder_requires_the_complete_known_set(): void
    {
        $this->actingAsAdmin();
        $ids = HomepageSection::query()->merchandising()->orderBy('id')->pluck('id')->all();
        $payload = collect(array_reverse($ids))->map(fn (int $id, int $position): array => ['id' => $id, 'sort_order' => $position])->all();

        $this->put(route('admin.homepage-sections.merchandising.reorder'), ['sections' => $payload])->assertRedirect();
        $this->assertSame(array_reverse($ids), HomepageSection::query()->merchandising()->orderBy('sort_order')->pluck('id')->all());
        $this->assertSame(
            ['custom_cta', 'featured_places', 'featured_tours', 'featured_destinations', 'featured_cities', 'hero_search'],
            array_column(app(HomepageService::class)->compose()['sections'], 'type')
        );

        $this->putJson(route('admin.homepage-sections.merchandising.reorder'), ['sections' => array_slice($payload, 1)])
            ->assertUnprocessable();
    }

    public function test_homepage_permission_is_granted_to_content_staff_and_denied_to_vendor(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $manager->assignRole('content-manager');
        $this->actingAs($manager);
        $this->get(route('admin.homepage-sections.index'))->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'vendor']));
        $this->get(route('admin.homepage-sections.index'))->assertForbidden();
    }

    public function test_generic_homepage_seeder_is_idempotent_and_preserves_custom_configuration(): void
    {
        $section = $this->section('featured_destinations');
        $section->update([
            'settings' => ['title' => 'Curated destinations'],
            'item_limit' => 4,
            'source_mode' => 'manual',
        ]);
        $beforeCount = HomepageSection::query()->merchandising()->count();

        $this->seed(HomepageSectionSeeder::class);
        $this->seed(HomepageSectionSeeder::class);

        $this->assertSame($beforeCount, HomepageSection::query()->merchandising()->count());
        $this->assertSame(1, HomepageSection::query()->merchandising()->where('section_type', 'featured_destinations')->count());
        $this->assertSame('Curated destinations', $section->fresh()->settings['title']);
        $this->assertSame(4, $section->fresh()->item_limit);
        $this->assertSame('manual', $section->fresh()->source_mode);
    }

    public function test_module_aware_defaults_keep_shared_sections_and_restore_commerce_sections(): void
    {
        Setting::setValue('modules.hotels.enabled', '0');
        Setting::setValue('modules.tours.enabled', '0');

        $types = array_column(app(HomepageService::class)->compose()['sections'], 'type');

        $this->assertNotContains('featured_hotels', $types);
        $this->assertNotContains('top_rated_hotels', $types);
        $this->assertNotContains('featured_tours', $types);
        $this->assertContains('featured_cities', $types);
        $this->assertContains('featured_destinations', $types);
        $this->assertContains('featured_places', $types);

        $this->assertDatabaseHas('homepage_sections', ['section_type' => 'featured_hotels']);
        $this->assertDatabaseHas('homepage_sections', ['section_type' => 'featured_tours']);

        Setting::setValue('modules.hotels.enabled', '1');
        Setting::setValue('modules.tours.enabled', '1');

        $restored = array_column(app(HomepageService::class)->compose()['sections'], 'type');
        $this->assertContains('featured_hotels', $restored);
        $this->assertContains('top_rated_hotels', $restored);
        $this->assertContains('featured_tours', $restored);
    }

    public function test_item_limits_and_cta_urls_are_safely_bounded(): void
    {
        $this->actingAsAdmin();
        $section = $this->section('featured_destinations');

        $this->putJson(route('admin.homepage-sections.update', $section), ['item_limit' => 25])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('item_limit');

        $this->putJson(route('admin.homepage-sections.update', $section), ['item_limit' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('item_limit');

        $cta = $this->section('custom_cta');
        $this->putJson(route('admin.homepage-sections.update', $cta), [
            'settings' => ['cta_url' => 'javascript:alert(1)'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.cta_url');

        $this->put(route('admin.homepage-sections.update', $section), ['item_limit' => 24])
            ->assertRedirect();
        $this->assertSame(24, $section->fresh()->item_limit);
    }

    public function test_public_dto_is_compact_and_homepage_money_keeps_authoritative_amount(): void
    {
        config()->set('modules.hotels.available', true);
        Setting::setValue('modules.hotels.enabled', '1');
        $this->seed(CurrencySeeder::class);
        $property = Property::factory()->create([
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
            'currency' => 'USD',
            'is_featured' => true,
        ]);
        $room = HotelRoomType::factory()->create([
            'property_id' => $property->id,
            'status' => RoomTypeStatus::Active->value,
        ]);
        HotelRatePlan::factory()->create([
            'property_id' => $property->id,
            'hotel_room_type_id' => $room->id,
            'currency' => 'USD',
            'base_rate' => '100.00',
            'is_active' => true,
        ]);
        ExchangeRate::updateOrCreate(
            ['base_currency_code' => CurrencyConversionService::fxBase(), 'quote_currency_code' => 'INR'],
            ['rate' => '83.00', 'source' => 'manual', 'fetched_at' => now()],
        );
        Currency::query()->update(['is_default_display' => false]);
        Currency::query()->where('code', 'INR')->update(['is_default_display' => true]);
        CurrencyRegistry::forgetCache();

        $this->assertSame('USD', $property->fresh()->currency);
        $hotel = collect(app(HomepageService::class)->compose()['sections'])
            ->firstWhere('type', 'featured_hotels')['items'][0];

        $this->assertSame('100.00', $hotel['starting_price']['amount']);
        $this->assertSame('USD', $hotel['starting_price']['currency']);
        $this->assertArrayHasKey('display_money', $hotel);
        $this->assertSame('INR', $hotel['display_money']['display_currency']);
        $this->assertTrue($hotel['display_money']['conversion_applied']);
        $this->assertArrayNotHasKey('settings', $hotel);
        $this->assertArrayNotHasKey('vendor', $hotel);
        $this->assertSame('USD', $property->fresh()->currency);
    }

    public function test_top_rated_hotels_use_approved_reviews_only(): void
    {
        config()->set('modules.hotels.available', true);
        Setting::setValue('modules.hotels.enabled', '1');

        $approved = HotelReview::factory()->approved()->fiveStar()->create();
        $pending = HotelReview::factory()->pending()->fiveStar()->create();
        $approved->property->forceFill([
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
        ])->save();
        $pending->property->forceFill([
            'status' => PropertyStatus::Published->value,
            'published_at' => now(),
        ])->save();

        $hotelSection = collect(app(HomepageService::class)->compose()['sections'])
            ->firstWhere('type', 'top_rated_hotels');
        $ids = array_column($hotelSection['items'], 'id');

        $this->assertContains($approved->property_id, $ids);
        $this->assertNotContains($pending->property_id, $ids);
        $this->assertSame(5, (int) $hotelSection['items'][0]['rating_average']);
    }

    public function test_admin_homepage_routes_preserve_the_deployment_base(): void
    {
        URL::forceRootUrl('http://localhost/code');

        $this->assertStringContainsString('/code/admin/homepage-sections', route('admin.homepage-sections.index'));
        $this->assertStringContainsString('/code/admin/homepage-sections/merchandising/reorder', route('admin.homepage-sections.merchandising.reorder'));
    }
}
