<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Destination;
use App\Models\HotelAmenity;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\State;
use App\Models\VendorProfile;
use App\Services\HotelCustomFieldService;
use App\Services\HotelPropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin property management (12B.1).
 *
 * Platform-wide: all properties, direct publish, featured control,
 * vendor assignment. Staff authorization via the central route map.
 */
class PropertyController extends Controller
{
    public function __construct(
        protected HotelPropertyService $properties,
        protected HotelCustomFieldService $customFields,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'vendor_id', 'property_type_id', 'city_id', 'status', 'featured']);

        $query = Property::with(['propertyType:id,name', 'vendorProfile:id,business_name', 'city:id,name'])
            ->withCount('images')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = '%'.mb_substr(trim((string) $filters['search']), 0, 80).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('slug', 'like', $search));
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_profile_id', (int) $filters['vendor_id']);
        }

        if (! empty($filters['property_type_id'])) {
            $query->where('property_type_id', (int) $filters['property_type_id']);
        }

        if (! empty($filters['city_id'])) {
            $query->where('city_id', (int) $filters['city_id']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], PropertyStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['featured']) && $filters['featured'] !== '') {
            $query->where('is_featured', (bool) $filters['featured']);
        }

        return Inertia::render('Admin/Hotel/Properties/Index', [
            'properties' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'statuses' => collect(PropertyStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'types' => PropertyType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'cities' => City::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Hotel/Properties/Form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(HotelPropertyService::rules());
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues(
            'property', $request->input('custom_fields', []), (int) $data['property_type_id']
        );

        $vendorId = $request->input('vendor_profile_id');
        abort_unless($vendorId === null || VendorProfile::whereKey($vendorId)->exists(), 422, 'Unknown vendor.');

        $property = DB::transaction(function () use ($data, $custom, $vendorId, $request): Property {
            $property = $this->properties->create(
                $data,
                $request->user(),
                $vendorId !== null ? (int) $vendorId : null,
                $request->boolean('publish_directly') && $request->user()->can('hotel.properties.publish')
            );
            $this->customFields->saveValues('property', $property->id, $custom);

            return $property;
        });

        return redirect(route('admin.hotel.properties.edit', $property, absolute: false))
            ->with('flash', 'Property created.');
    }

    public function show(Property $property): Response
    {
        $property->load(['propertyType', 'vendorProfile:id,business_name', 'city:id,name', 'state:id,name', 'amenities:id,name,category', 'images']);

        return Inertia::render('Admin/Hotel/Properties/Show', ['property' => $property]);
    }

    public function edit(Property $property): Response
    {
        return Inertia::render('Admin/Hotel/Properties/Form', $this->formData($property));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate(HotelPropertyService::rules($property));
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues(
            'property', $request->input('custom_fields', []), (int) $data['property_type_id']
        );

        if ($request->filled('vendor_profile_id')) {
            abort_unless(VendorProfile::whereKey($request->input('vendor_profile_id'))->exists(), 422, 'Unknown vendor.');
            $property->forceFill(['vendor_profile_id' => (int) $request->input('vendor_profile_id')])->save();
        } elseif ($request->boolean('detach_vendor')) {
            $property->forceFill(['vendor_profile_id' => null])->save();
        }

        DB::transaction(function () use ($property, $data, $custom, $request): void {
            $this->properties->update($property->fresh(), $data, $request->user());
            $this->customFields->saveValues('property', $property->id, $custom);
        });

        return back()->with('flash', 'Property updated.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->properties->delete($property);

        return redirect(route('admin.hotel.properties.index', absolute: false))
            ->with('flash', 'Property archived.');
    }

    public function publish(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->can('hotel.properties.publish'), 403);
        $this->properties->publish($property, $request->user());

        return back()->with('flash', 'Property published.');
    }

    public function reject(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->can('hotel.properties.publish'), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->properties->reject($property, $request->user(), $data['note'] ?? null);

        return back()->with('flash', 'Property rejected.');
    }

    public function deactivate(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->can('hotel.properties.publish'), 403);
        $this->properties->deactivate($property, $request->user());

        return back()->with('flash', 'Property deactivated.');
    }

    public function storeImage(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:150'],
        ]);

        $this->properties->addImage($property, $data['image'], $data['alt_text'] ?? null);

        return back()->with('flash', 'Gallery image added.');
    }

    public function destroyImage(Property $property, PropertyImage $image): RedirectResponse
    {
        $this->properties->removeImage($property, $image);

        return back()->with('flash', 'Gallery image removed.');
    }

    public function primaryImage(Property $property, PropertyImage $image): RedirectResponse
    {
        $this->properties->setPrimaryImage($property, $image);

        return back()->with('flash', 'Cover image updated.');
    }

    public function cities(Request $request): JsonResponse
    {
        $query = City::active()->with('state:id,name')->orderBy('name')->limit(50);

        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }

        if ($request->filled('state_id')) {
            $query->where('state_id', (int) $request->input('state_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.mb_substr(trim((string) $request->input('search')), 0, 60).'%');
        }

        return response()->json($query->get(['id', 'country_id', 'name', 'state_id'])->map(fn (City $city): array => [
            'id' => $city->id,
            'name' => $city->name,
            'state' => $city->state?->name,
        ]));
    }

    /**
     * Bounded optional destination/area lookup for the property form
     * (12B.4.1). Scoped by country/state/city; active only.
     */
    public function destinations(Request $request): JsonResponse
    {
        $query = Destination::active()->with('city:id,name')->ordered()->limit(50);

        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }

        if ($request->filled('state_id')) {
            $query->where('state_id', (int) $request->input('state_id'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', (int) $request->input('city_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.mb_substr(trim((string) $request->input('search')), 0, 60).'%');
        }

        return response()->json($query->get(['id', 'country_id', 'state_id', 'city_id', 'name'])->map(fn (Destination $destination): array => [
            'id' => $destination->id,
            'name' => $destination->name,
            'city' => $destination->city?->name,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Property $property = null): array
    {
        $property?->load(['amenities:id', 'images']);

        return [
            'adminBreadcrumbs' => [
                ['label' => 'Dashboard', 'href' => '/admin/dashboard'],
                ['label' => 'Hotels', 'href' => '/admin/hotel/properties'],
                ['label' => 'Properties', 'href' => '/admin/hotel/properties'],
                ['label' => $property ? 'Edit Property' : 'New Property'],
            ],
            'property' => $property,
            'types' => PropertyType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'amenities' => HotelAmenity::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'category']),
            'countries' => Country::active()->ordered()->get(['id', 'name']),
            'states' => State::active()->ordered()->get(['id', 'country_id', 'name']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'statuses' => collect(PropertyStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
            'canPublish' => auth()->user()?->can('hotel.properties.publish') ?? false,
            'customFields' => $property
                ? $this->customFields->formSchema('property', $property->id, $property->property_type_id)
                : $this->customFields->blankSchema('property'),
        ];
    }
}
