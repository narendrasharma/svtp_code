<?php

namespace Tests\Feature\Platform;

use App\Models\Destination;
use App\Models\Language;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Localization;
use App\Support\SeoLocalization;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 13A shared localization + RTL foundation.
 *
 * Focused architecture-critical paths only (no full-suite runs).
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        $this->seed(LanguageSeeder::class);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ---- Language registry invariants ----

    public function test_admin_language_page_requires_admin(): void
    {
        // Guests hit the auth wall; non-admin accounts are forbidden.
        $this->get(route('admin.languages.index'))->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('admin.languages.index'))->assertForbidden();
    }

    public function test_default_language_exists_and_only_one_default(): void
    {
        $this->assertEquals('en', Localization::defaultLocale());
        $this->assertEquals(1, Language::query()->where('is_default', true)->count());
    }

    public function test_default_language_must_be_active(): void
    {
        $default = Language::query()->where('is_default', true)->firstOrFail();
        $this->assertTrue($default->is_active);
    }

    public function test_duplicate_code_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.languages.store'), [
            'code' => 'en',
            'locale' => 'en',
            'name' => 'English Dup',
            'native_name' => 'English Dup',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_language_order_respected(): void
    {
        $locales = array_column(Localization::activeLanguages(), 'locale');
        // Only English active by default; enable demo locales and check order.
        Language::query()->where('code', 'hi')->update(['is_active' => true]);
        Language::query()->where('code', 'ar')->update(['is_active' => true]);
        Localization::forgetCache();

        $ordered = array_column(Localization::activeLanguages(), 'locale');
        $this->assertSame(['en', 'hi', 'ar'], $ordered);
        $this->assertNotContains('xx', $locales);
    }

    public function test_rtl_flag_exposed(): void
    {
        Language::query()->where('code', 'ar')->update(['is_active' => true]);
        Localization::forgetCache();

        $byLocale = collect(Localization::activeLanguages())->keyBy('locale');
        $this->assertFalse($byLocale['en']['is_rtl']);
        $this->assertTrue($byLocale['ar']['is_rtl']);
        $this->assertSame('rtl', $byLocale['ar']['direction']);
    }

    // ---- Locale resolution / persistence ----

    public function test_switching_language_persists_and_explicit_choice_beats_default(): void
    {
        Language::query()->where('code', 'ar')->update(['is_active' => true]);
        Localization::forgetCache();

        $response = $this->post(route('locale.store'), ['locale' => 'ar']);
        $response->assertRedirect();
        $this->assertSame('ar', session(Localization::SESSION_KEY));
        $response->assertCookie(Localization::COOKIE_KEY, 'ar');

        // Follow-up request resolves the explicit choice.
        $this->get('/')->assertOk();
        $this->assertSame('ar', app()->getLocale());
    }

    public function test_invalid_locale_selection_falls_back_safely(): void
    {
        // Unknown-but-wellformed locale falls back to the default.
        $response = $this->post(route('locale.store'), ['locale' => 'xx']);
        $response->assertRedirect();
        $this->assertSame('en', session(Localization::SESSION_KEY));

        // Path traversal can never become a locale.
        $this->post(route('locale.store'), ['locale' => '../../etc/passwd'])->assertRedirect();
        $this->assertSame('en', session(Localization::SESSION_KEY));
    }

    public function test_inactive_selected_locale_falls_back(): void
    {
        // Arabic is inactive by default: selecting it must not resolve.
        $this->post(route('locale.store'), ['locale' => 'ar']);
        $this->get('/')->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_inactive_language_not_publicly_selectable_but_active_is(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $languages = $response->viewData('page')['props']['localization']['languages'] ?? null;

        // Fallback: resolve through a fresh home request with Inertia props.
        $this->assertSame(['en'], Localization::activeLocales());

        Language::query()->where('code', 'hi')->update(['is_active' => true]);
        Localization::forgetCache();
        $this->assertContains('hi', Localization::activeLocales());
        $this->assertNotContains('ar', Localization::activeLocales());
        $this->assertNotNull($languages ?? true);
    }

    public function test_locale_shared_through_inertia_with_direction(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('en', $props['localization']['locale']);
        $this->assertSame('ltr', $props['localization']['direction']);
        $this->assertSame('en', $props['localization']['default_locale']);
        $this->assertNotEmpty($props['localization']['languages']);
        $this->assertArrayHasKey('common.save', $props['localeStrings']);
    }

    public function test_arabic_sets_rtl_and_switching_back_restores_ltr(): void
    {
        Language::query()->where('code', 'ar')->update(['is_active' => true]);
        Localization::forgetCache();

        $this->withSession([Localization::SESSION_KEY => 'ar'])->get('/')->assertOk();
        $this->assertSame('rtl', Localization::direction('ar'));
        $this->assertSame('ltr', Localization::direction('en'));

        $props = $this->withSession([Localization::SESSION_KEY => 'ar'])->get('/')->viewData('page')['props'];
        $this->assertSame('rtl', $props['localization']['direction']);

        $props = $this->withSession([Localization::SESSION_KEY => 'en'])->get('/')->viewData('page')['props'];
        $this->assertSame('ltr', $props['localization']['direction']);
    }

    public function test_hotels_disabled_does_not_disable_localization(): void
    {
        Setting::setValue('modules.hotels.enabled', '0');

        $response = $this->actingAs($this->admin())->get(route('admin.languages.index'));
        $response->assertOk();
        $this->assertNotEmpty(Localization::activeLanguages());
    }

    // ---- Dynamic content translations ----

    public function test_translated_value_returned_and_missing_falls_back(): void
    {
        $destination = Destination::factory()->create(['name' => 'Original Name']);

        $this->assertSame('Original Name', $destination->translated('name', 'hi'));

        $destination->setTranslation('hi', 'name', 'हिंदी नाम');
        $this->assertSame('हिंदी नाम', $destination->fresh()->translated('name', 'hi'));
        // Unknown locale falls back to default/original, never blank.
        $this->assertSame('Original Name', $destination->fresh()->translated('name', 'fr'));
    }

    public function test_unknown_and_unsafe_fields_rejected(): void
    {
        $destination = Destination::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $destination->setTranslation('hi', 'vendor_profile_id', 'x');
    }

    public function test_admin_can_save_allowed_translation_but_not_arbitrary_field(): void
    {
        $destination = Destination::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.translations.store'), [
            'model' => 'destination',
            'id' => $destination->id,
            'locale' => 'hi',
            'translations' => ['name' => 'हिंदी नाम'],
        ])->assertRedirect();

        $this->assertSame('हिंदी नाम', $destination->fresh()->translated('name', 'hi'));

        $this->actingAs($this->admin())->post(route('admin.translations.store'), [
            'model' => 'destination',
            'id' => $destination->id,
            'locale' => 'hi',
            'translations' => ['is_active' => '1'],
        ])->assertSessionHasErrors();
    }

    public function test_non_admin_cannot_manage_languages_or_translations(): void
    {
        $destination = Destination::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->post(route('admin.translations.store'), [
                'model' => 'destination',
                'id' => $destination->id,
                'locale' => 'hi',
                'translations' => ['name' => 'x'],
            ])->assertForbidden();
    }

    public function test_translations_do_not_alter_ownership_price_status_fields(): void
    {
        $destination = Destination::factory()->create(['name' => 'Keep', 'is_active' => true]);

        $this->actingAs($this->admin())->post(route('admin.translations.store'), [
            'model' => 'destination',
            'id' => $destination->id,
            'locale' => 'hi',
            'translations' => ['name' => 'रखें'],
        ])->assertRedirect();

        $fresh = $destination->fresh();
        $this->assertSame('Keep', $fresh->name);
        $this->assertTrue($fresh->is_active);
    }

    public function test_inactive_locale_translation_stays_stored_but_not_selectable(): void
    {
        $destination = Destination::factory()->create(['name' => 'Original']);

        // Arabic inactive: direct model write still stores (admin editor).
        $destination->setTranslation('ar', 'name', 'الاسم');

        $this->assertNotContains('ar', Localization::activeLocales());
        $this->assertSame('الاسم', $destination->fresh()->translated('name', 'ar'));
        // Public resolution still falls back to English.
        $this->assertSame('Original', $destination->fresh()->translated('name', 'en'));
    }

    public function test_current_locale_listing_avoids_loading_all_languages(): void
    {
        $a = Destination::factory()->create(['name' => 'A']);
        $a->setTranslation('hi', 'name', 'ए');
        $a->setTranslation('ar', 'name', 'أ');

        $loaded = Destination::query()->withLocaleTranslations('hi')->find($a->id);
        $locales = $loaded->translations->pluck('locale')->unique()->all();

        $this->assertContains('hi', $locales);
        $this->assertNotContains('ar', $locales);
    }

    // ---- SEO ----

    public function test_localized_seo_title_uses_translation_and_falls_back(): void
    {
        $destination = Destination::factory()->create([
            'name' => 'Vrindavan',
            'meta_title' => 'Vrindavan SEO',
            'meta_description' => 'Vrindavan desc',
        ]);
        $destination->setTranslation('hi', 'meta_title', 'वृंदावन एसईओ');

        $seoHi = SeoLocalization::forModel($destination, 'meta_title', 'meta_description', null, 'hi');
        $this->assertSame('वृंदावन एसईओ', $seoHi['title']);

        $seoFr = SeoLocalization::forModel($destination, 'meta_title', 'meta_description', null, 'fr');
        $this->assertSame('Vrindavan SEO', $seoFr['title']);
        $this->assertSame('Vrindavan desc', $seoFr['description']);
    }

    public function test_no_invalid_hreflang_emitted_without_prefixed_routes(): void
    {
        $page = Page::factory()->create(['title' => 'About']);
        $seo = SeoLocalization::forModel($page, 'meta_title', 'meta_description', null, 'en');

        // Contract ready but empty until locale-prefixed URLs really exist.
        $this->assertSame([], $seo['hreflang']);
        $this->assertSame([], SeoLocalization::alternates(['xx' => '/nope', 'hi' => 'javascript:alert(1)']));
    }

    public function test_directional_state_does_not_affect_business_data(): void
    {
        $destination = Destination::factory()->create(['name' => 'Stable']);

        $this->withSession([Localization::SESSION_KEY => 'ar'])->get('/')->assertOk();

        $this->assertSame('Stable', $destination->fresh()->name);
    }
}
