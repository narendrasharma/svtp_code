<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Support\Localization;
use App\Support\SeoLocalization;
use Inertia\Inertia;
use Inertia\Response;

class DestinationController extends Controller
{
    public function index(): Response
    {
        // Only show active destinations to the public
        $destinations = Destination::with('city:id,name')
            ->withLocaleTranslations()
            ->withCount(['places', 'tourPackages' => fn ($query) => $query->publiclyVisible()])
            ->where('is_active', true) // <-- hide inactive destinations
            ->orderBy('name')
            ->get()
            ->map(fn (Destination $destination) => $this->localizedCard($destination));

        return Inertia::render('Static/Destinations', [
            'destinations' => $destinations,
        ]);
    }

    public function show(Destination $destination): Response
    {
        // If the destination is inactive, treat it as not found
        if (! $destination->is_active) {
            abort(404);
        }

        $destination->load([
            'city:id,name',
            'places' => fn ($query) => $query->orderBy('name'),
            'tourPackages' => fn ($query) => $query->publiclyVisible()
                ->with('city:id,name')
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->orderByDesc('is_featured')
                ->orderBy('title'),
        ]);
        $destination->loadMissing('translations');

        $locale = Localization::currentLocale();
        $seo = SeoLocalization::forModel(
            $destination,
            'meta_title',
            'meta_description',
            route('destinations.show', $destination, absolute: true),
            $locale
        );

        // Localized SEO title falls back to the destination name, never blank.
        $seo['title'] ??= $destination->translated('name', $locale) ?? $destination->name;

        return Inertia::render('Static/Destination', [
            'destination' => $this->localizedCard($destination, true),
            'localizedSeo' => $seo,
        ]);
    }

    /**
     * Phase 13A: expose translated display fields with safe fallback.
     * Original columns are always preserved (translations never mutate).
     *
     * @return array<string, mixed>
     */
    protected function localizedCard(Destination $destination, bool $full = false): array
    {
        $locale = Localization::currentLocale();

        $card = $destination->toArray();
        $card['display_name'] = $destination->translated('name', $locale) ?? $destination->name;
        $card['display_description'] = $destination->translated('description', $locale) ?? $destination->description;
        $card['display_meta_title'] = $destination->translated('meta_title', $locale) ?? $destination->meta_title;
        $card['display_meta_description'] = $destination->translated('meta_description', $locale) ?? $destination->meta_description;
        $card['locale'] = $locale;

        if (! $full) {
            unset($card['translations']);
        }

        return $card;
    }
}
