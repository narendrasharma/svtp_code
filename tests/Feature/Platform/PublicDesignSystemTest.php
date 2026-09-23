<?php

namespace Tests\Feature\Platform;

use App\Models\Language;
use App\Models\Setting;
use App\Support\Localization;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicDesignSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost/code');
        URL::forceRootUrl('http://localhost');
        $this->seed(LanguageSeeder::class);
        $this->seed(CurrencySeeder::class);
    }

    public function test_public_homepage_receives_the_shared_shell_contract(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('localization.locale')
                ->has('localization.direction')
                ->has('localization.languages')
                ->has('currency.currencies')
                ->has('platformModules')
                ->has('homepage.sections')
            );
    }

    public function test_public_shell_contract_propagates_rtl_locale(): void
    {
        Language::query()->where('code', 'ar')->update(['is_active' => true]);
        Localization::forgetCache();

        $this->withSession(['locale' => 'ar'])
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.locale', 'ar')
                ->where('localization.direction', 'rtl')
            );
    }

    public function test_public_module_contract_preserves_shared_state_when_commerce_is_disabled(): void
    {
        Setting::setValue('modules.hotels.enabled', '0');
        Setting::setValue('modules.tours.enabled', '0');
        Setting::setValue('modules.taxi.enabled', '0');

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('platformModules', fn ($modules): bool => collect($modules)
                    ->whereIn('key', ['hotels', 'tours', 'taxi'])
                    ->every(fn (array $module): bool => $module['enabled'] === false))
                ->has('localization')
                ->has('currency')
            );
    }
}
