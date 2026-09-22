<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Place;
use App\Models\TourPackage;
use App\Support\ModuleManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Phase 11.5A: the public catalogue search only covers the tours
        // module today — it goes quiet while tours are disabled.
        if (app(ModuleManager::class)->isDisabled(ModuleManager::TOURS)) {
            return response()->json($this->emptyResults());
        }

        $query = $request->input('q', '');

        if (! is_string($query)) {
            return response()->json($this->emptyResults());
        }

        $search = Str::of($query)->squish()->limit(80, '')->toString();

        if (Str::length($search) < 2) {
            return response()->json($this->emptyResults());
        }

        $fullTextSearch = collect(preg_split('/[^\pL\pN]+/u', $search, flags: PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $keyword): string => $keyword.'*')
            ->join(' ');

        if ($fullTextSearch === '') {
            return response()->json($this->emptyResults());
        }

        $tours = TourPackage::query()
            ->publiclyVisible()
            ->with('city:id,name')
            ->where(function (Builder $query) use ($fullTextSearch, $search): void {
                $this->whereKeywords($query, ['title', 'overview', 'meta_description'], $fullTextSearch, $search)
                    ->orWhereHas('city', fn (Builder $cityQuery) => $cityQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinations', function (Builder $destinationQuery) use ($fullTextSearch, $search): void {
                        $this->whereKeywords($destinationQuery, ['name', 'description', 'meta_description'], $fullTextSearch, $search);
                    })
                    ->orWhereHas('places', function (Builder $placeQuery) use ($fullTextSearch, $search): void {
                        $this->whereKeywords($placeQuery, ['name', 'description', 'meta_description'], $fullTextSearch, $search);
                    });
            })
            ->orderBy('title')
            ->limit(5)
            ->get(['id', 'city_id', 'title', 'slug', 'duration_days', 'overview', 'meta_description', 'cover_image'])
            ->map(fn (TourPackage $tour): array => [
                'id' => $tour->id,
                'title' => $tour->title,
                'type' => 'Tour',
                'subtitle' => collect([
                    $tour->city?->name,
                    $tour->duration_days.' day'.($tour->duration_days === 1 ? '' : 's'),
                ])->filter()->join(' · '),
                'context' => $this->shortContext(
                    $tour->overview ?: $tour->meta_description,
                    $tour->city ? 'Tour package in '.$tour->city->name : 'Tour package'
                ),
                'image' => $tour->cover_image,
                'url' => route('packages.show', $tour, false),
            ]);

        $destinations = Destination::query()
            ->with('city:id,name')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($fullTextSearch, $search): void {
                $this->whereKeywords($query, ['name', 'description', 'meta_description'], $fullTextSearch, $search)
                    ->orWhereHas('city', fn (Builder $cityQuery) => $cityQuery->where('name', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'city_id', 'name', 'slug', 'description', 'meta_description', 'image'])
            ->map(fn (Destination $destination): array => [
                'id' => $destination->id,
                'title' => $destination->name,
                'type' => 'Destination',
                'subtitle' => $destination->city?->name,
                'context' => $this->shortContext(
                    $destination->description ?: $destination->meta_description,
                    'Explore tours and attractions'.($destination->city ? ' in '.$destination->city->name : '')
                ),
                'image' => $destination->image,
                'url' => route('destinations.show', $destination, false),
            ]);

        $places = Place::query()
            ->with('destination:id,name')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($fullTextSearch, $search): void {
                $this->whereKeywords($query, ['name', 'description', 'meta_description'], $fullTextSearch, $search)
                    ->orWhereHas('destination', function (Builder $destinationQuery) use ($fullTextSearch, $search): void {
                        $this->whereKeywords($destinationQuery, ['name', 'description', 'meta_description'], $fullTextSearch, $search);
                    });
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'destination_id', 'name', 'slug', 'description', 'meta_description', 'image'])
            ->map(fn (Place $place): array => [
                'id' => $place->id,
                'title' => $place->name,
                'type' => 'Place',
                'subtitle' => $place->destination?->name,
                'context' => $this->shortContext(
                    $place->description ?: $place->meta_description,
                    'Attraction'.($place->destination ? ' in '.$place->destination->name : '')
                ),
                'image' => $place->image,
                'url' => route('places.show', $place, false),
            ]);

        return response()->json([
            'tours' => $tours,
            'destinations' => $destinations,
            'places' => $places,
        ]);
    }

    /**
     * @return array{tours: array<never>, destinations: array<never>, places: array<never>}
     */
    private function emptyResults(): array
    {
        return ['tours' => [], 'destinations' => [], 'places' => []];
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function whereKeywords(
        Builder $query,
        array $columns,
        string $fullTextSearch,
        string $fallbackSearch
    ): Builder {
        if ($this->supportsFullTextSearch() && $fullTextSearch !== '') {
            return $query->whereFullText($columns, $fullTextSearch, ['mode' => 'boolean']);
        }

        return $query->where(function (Builder $fallbackQuery) use ($columns, $fallbackSearch): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $fallbackQuery->{$method}($column, 'like', "%{$fallbackSearch}%");
            }
        });
    }

    private function supportsFullTextSearch(): bool
    {
        return in_array(TourPackage::query()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function shortContext(?string $description, string $fallback): string
    {
        return Str::of($description ?: $fallback)
            ->stripTags()
            ->squish()
            ->limit(105)
            ->toString();
    }
}
