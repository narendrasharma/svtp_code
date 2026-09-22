<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelAmenity;
use App\Models\HotelBedType;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin catalogue management: property types + amenities (12B.1).
 * Global definitions — vendors only attach active ones to own properties.
 */
class CatalogueController extends Controller
{
    public function types(Request $request): Response
    {
        return Inertia::render('Admin/Hotel/Types/Index', [
            'types' => PropertyType::orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $this->typeData($request);
        $data['slug'] = $this->uniqueSlug(PropertyType::class, $data['slug'] ?? null, $data['name']);
        PropertyType::create($data);

        return back()->with('flash', 'Property type created.');
    }

    public function updateType(Request $request, PropertyType $type): RedirectResponse
    {
        $data = $this->typeData($request, $type->id);
        $data['slug'] = $this->uniqueSlug(PropertyType::class, $data['slug'] ?? $type->slug, $data['name'] ?? $type->name, $type->id);
        $type->update($data);

        return back()->with('flash', 'Property type updated.');
    }

    public function toggleType(PropertyType $type): RedirectResponse
    {
        $type->update(['is_active' => ! $type->is_active]);

        return back()->with('flash', 'Property type status updated.');
    }

    public function amenities(Request $request): Response
    {
        return Inertia::render('Admin/Hotel/Amenities/Index', [
            'amenities' => HotelAmenity::orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString(),
            'categories' => HotelAmenity::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function storeAmenity(Request $request): RedirectResponse
    {
        $data = $this->amenityData($request);
        $data['slug'] = $this->uniqueSlug(HotelAmenity::class, $data['slug'] ?? null, $data['name']);
        HotelAmenity::create($data);

        return back()->with('flash', 'Amenity created.');
    }

    public function updateAmenity(Request $request, HotelAmenity $amenity): RedirectResponse
    {
        $data = $this->amenityData($request, $amenity->id);
        $data['slug'] = $this->uniqueSlug(HotelAmenity::class, $data['slug'] ?? $amenity->slug, $data['name'] ?? $amenity->name, $amenity->id);
        $amenity->update($data);

        return back()->with('flash', 'Amenity updated.');
    }

    public function toggleAmenity(HotelAmenity $amenity): RedirectResponse
    {
        $amenity->update(['is_active' => ! $amenity->is_active]);

        return back()->with('flash', 'Amenity status updated.');
    }

    public function bedTypes(): Response
    {
        return Inertia::render('Admin/Hotel/BedTypes/Index', [
            'bedTypes' => HotelBedType::orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function storeBedType(Request $request): RedirectResponse
    {
        $data = $this->bedTypeData($request);
        $data['slug'] = $this->uniqueSlug(HotelBedType::class, $data['slug'] ?? null, $data['name']);
        HotelBedType::create($data);

        return back()->with('flash', 'Bed type created.');
    }

    public function updateBedType(Request $request, HotelBedType $bedType): RedirectResponse
    {
        $data = $this->bedTypeData($request, $bedType->id);
        $data['slug'] = $this->uniqueSlug(HotelBedType::class, $data['slug'] ?? $bedType->slug, $data['name'] ?? $bedType->name, $bedType->id);
        $bedType->update($data);

        return back()->with('flash', 'Bed type updated.');
    }

    public function toggleBedType(HotelBedType $bedType): RedirectResponse
    {
        $bedType->update(['is_active' => ! $bedType->is_active]);

        return back()->with('flash', 'Bed type status updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:property_types,slug'.($ignoreId ? ','.$ignoreId : '')],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9\- ]+$/i'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function amenityData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:hotel_amenities,slug'.($ignoreId ? ','.$ignoreId : '')],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9\- ]+$/i'],
            'category' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function bedTypeData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:hotel_bed_types,slug'.($ignoreId ? ','.$ignoreId : '')],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function uniqueSlug(string $model, ?string $desired, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim($desired !== null && trim($desired) !== '' ? $desired : $name) ?: 'item');
        $base = mb_substr($base !== '' ? $base : 'item', 0, 80);
        $slug = $base;
        $counter = 2;

        while ($model::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = mb_substr($base, 0, 90).'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
