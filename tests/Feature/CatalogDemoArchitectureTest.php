<?php

namespace Tests\Feature;

use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogDemoArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_destination_filter_uses_seeded_relationships(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->get('/packages?destination=agra')->assertInertia(fn (Assert $page) => $page
            ->has('packages.data', 4)
            ->where('filters.destination', 'agra'));
    }

    public function test_place_filter_uses_seeded_relationships(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->get('/packages?place=taj-mahal')->assertInertia(fn (Assert $page) => $page
            ->has('packages.data', 4)
            ->where('filters.place', 'taj-mahal'));
    }

    public function test_tag_filter_uses_seeded_relationships(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->get('/packages?tags%5B0%5D=senior-friendly')->assertInertia(fn (Assert $page) => $page
            ->has('packages.data', 2)
            ->where('filters.tags', ['senior-friendly']));
    }

    public function test_hero_search_receives_seeded_internal_destinations(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('destinations', 6));
    }

    public function test_global_search_finds_seeded_places_and_related_tours(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->getJson('/search?q=Prem%20Mandir')->assertOk()
            ->assertJsonFragment(['title' => 'Prem Mandir', 'type' => 'Place'])
            ->assertJsonCount(5, 'tours');
    }

    public function test_destination_and_place_pages_show_seeded_related_tours(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        $this->get('/destinations/agra')->assertInertia(fn (Assert $page) => $page
            ->where('destination.slug', 'agra')
            ->has('destination.tour_packages', 4));
        $this->get('/places/taj-mahal')->assertInertia(fn (Assert $page) => $page
            ->where('place.slug', 'taj-mahal')
            ->has('place.tour_packages', 4));
    }
}
