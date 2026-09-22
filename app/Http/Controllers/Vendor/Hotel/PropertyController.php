<?php

namespace App\Http\Controllers\Vendor\Hotel;

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
use App\Support\HotelSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor property management (12B.1).
 *
 * Own properties only — every lookup is ownership-scoped (404 otherwise).
 * vendor_profile_id is resolved server-side, never from the browser.
 * Vendors cannot self-publish while approval is required and cannot
 * touch global type/amenity definitions.
 */
class PropertyController extends Controller
{
    public function __construct(
        protected HotelPropertyService $properties,
        protected HotelCustomFieldService $customFields,
    ) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        return $profile;
    }

    protected function scoped(Request $request, Property $property): Property
    {
        abort_unless((int) $property->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $property;
    }

    public function index(Request $request): Response
    {
        abort_unless(HotelSettings::enabled('hotel.vendor_can_create_properties') || $this->profile($request)->properties()->exists(), 403, 'Property creation is disabled.');

        $filters = $request->only(['search', 'status']);
        $profile = $this->profile($request);

        $query = Property::with(['propertyType:id,name'])
            ->withCount('images')
            ->where('vendor_profile_id', $profile->id)
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = '%'.mb_substr(trim((string) $filters['search']), 0, 80).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('slug', 'like', $search));
        }

        if (! empty($filters['status']) && in_array($filters['status'], PropertyStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        return Inertia::render('Vendor/Hotel/Properties/Index', [
            'properties' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'statuses' => collect(PropertyStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
            'canCreate' => HotelSettings::enabled('hotel.vendor_can_create_properties'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(HotelSettings::enabled('hotel.vendor_can_create_properties'), 403, 'Property creation is disabled.');

        return Inertia::render('Vendor/Hotel/Properties/Form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(HotelSettings::enabled('hotel.vendor_can_create_properties'), 403, 'Property creation is disabled.');
        $data = $request->validate(HotelPropertyService::rules());
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues(
            'property', $request->input('custom_fields', []), (int) $data['property_type_id']
        );

        $property = DB::transaction(function () use ($data, $custom, $request): Property {
            $property = $this->properties->create($data, $request->user(), $this->profile($request)->id);
            $this->customFields->saveValues('property', $property->id, $custom);

            return $property;
        });

        return redirect(route('vendor.hotel.properties.edit', $property, absolute: false))
            ->with('flash', 'Property saved as draft.');
    }

    public function show(Request $request, Property $property): Response
    {
        $property = $this->scoped($request, $property);
        $property->load(['propertyType', 'city:id,name', 'state:id,name', 'amenities:id,name,category', 'images']);

        return Inertia::render('Vendor/Hotel/Properties/Show', ['property' => $property]);
    }

    public function edit(Request $request, Property $property): Response
    {
        $property = $this->scoped($request, $property);

        return Inertia::render('Vendor/Hotel/Properties/Form', $this->formData($property));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $property = $this->scoped($request, $property);
        $data = $request->validate(HotelPropertyService::rules($property));
        $request->validate(['custom_fields' => ['nullable', 'array', 'max:100']]);
        $custom = $this->customFields->validateValues(
            'property', $request->input('custom_fields', []), (int) $data['property_type_id']
        );

        DB::transaction(function () use ($property, $data, $custom, $request): void {
            $this->properties->update($property, $data, $request->user());
            $this->customFields->saveValues('property', $property->id, $custom);
        });

        return back()->with('flash', 'Property updated.');
    }

    public function destroy(Request $request, Property $property): RedirectResponse
    {
        $property = $this->scoped($request, $property);
        $this->properties->delete($property);

        return redirect(route('vendor.hotel.properties.index', absolute: false))
            ->with('flash', 'Property archived.');
    }

    public function submit(Request $request, Property $property): RedirectResponse
    {
        $property = $this->scoped($request, $property);
        $this->properties->submit($property, $request->user());

        return back()->with('flash', HotelSettings::enabled('hotel.require_property_approval')
            ? 'Property submitted for review.'
            : 'Property published.');
    }

    public function storeImage(Request $request, Property $property): RedirectResponse
    {
        $property = $this->scoped($request, $property);
        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:150'],
        ]);
        $this->properties->addImage($property, $data['image'], $data['alt_text'] ?? null);

        return back()->with('flash', 'Gallery image added.');
    }

    public function destroyImage(Request $request, Property $property, PropertyImage $image): RedirectResponse
    {
        $property = $this->scoped($request, $property);
        $this->properties->removeImage($property, $image);

        return back()->with('flash', 'Gallery image removed.');
    }

    public function primaryImage(Request $request, Property $property, PropertyImage $image): RedirectResponse
    {
        $property = $this->scoped($request, $property);
        $this->properties->setPrimaryImage($property, $image);

        return back()->with('flash', 'Cover image updated.');
    }

    public function cities(Request $request): JsonResponse
    {
        $query = City::active()->orderBy('name')->limit(50);

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
        ]));
    }

    /**
     * Bounded optional destination/area lookup for the vendor property
     * form (12B.4.1). Read-only shared geography — vendors select, never
     * manage. Scoped by country/state/city; active only.
     */
    public function destinations(Request $request): JsonResponse
    {
        $query = Destination::active()->ordered()->limit(50);

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
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Property $property = null): array
    {
        $property?->load(['amenities:id', 'images']);

        return [
            'property' => $property,
            'types' => PropertyType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'amenities' => HotelAmenity::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'category']),
            'countries' => Country::active()->ordered()->get(['id', 'name']),
            'states' => State::active()->ordered()->get(['id', 'country_id', 'name']),
            'requireApproval' => HotelSettings::enabled('hotel.require_property_approval'),
            'customFields' => $property
                ? $this->customFields->formSchema('property', $property->id, $property->property_type_id)
                : $this->customFields->blankSchema('property'),
        ];
    }
}
