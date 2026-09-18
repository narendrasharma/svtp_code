<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\TaxiFuelType;
use App\Enums\TaxiVehicleDocumentType;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\NumberSeriesService;
use App\Services\TaxiDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor fleet: own vehicles only. Never another vendor's resources.
 */
class TaxiVehicleController extends Controller
{
    public function __construct(protected TaxiDocumentService $documents) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scoped(Request $request, Vehicle $vehicle): Vehicle
    {
        abort_unless((int) $vehicle->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $vehicle;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $vehicles = Vehicle::with('vehicleType:id,name')
            ->where('vendor_profile_id', $profile->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('reference', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('registration_number', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Vendor/Taxi/Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => $request->only(['search', 'status']),
            'statuses' => collect(VehicleStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->profile($request);

        return Inertia::render('Vendor/Taxi/Vehicles/Form', [
            'vehicle' => null,
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $this->profile($request);

        $vehicle = Vehicle::create([
            'reference' => app(NumberSeriesService::class)->next('taxi_vehicle'),
            'vendor_profile_id' => $profile->id,
            ...$this->validated($request, $profile),
        ]);

        return redirect()->route('vendor.taxi.vehicles.show', $vehicle)->with('flash', "Vehicle {$vehicle->reference} added.");
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        $vehicle = $this->scoped($request, $vehicle);
        $vehicle->load(['vehicleType:id,name', 'documents', 'unavailablePeriods']);

        return Inertia::render('Vendor/Taxi/Vehicles/Show', [
            'vehicle' => $vehicle,
            'documentTypes' => collect(TaxiVehicleDocumentType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function edit(Request $request, Vehicle $vehicle): Response
    {
        $vehicle = $this->scoped($request, $vehicle);

        return Inertia::render('Vendor/Taxi/Vehicles/Form', [
            'vehicle' => $vehicle,
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle = $this->scoped($request, $vehicle);
        $vehicle->update($this->validated($request, $vehicle->vendorProfile, $vehicle));

        return back()->with('flash', 'Vehicle updated.');
    }

    public function storeDocument(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle = $this->scoped($request, $vehicle);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(TaxiVehicleDocumentType::values())],
            'document_file' => ['required', 'file', 'max:5120'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ]);

        $this->documents->storeVehicleDocument(
            $vehicle,
            $validated['document_type'],
            $request->file('document_file'),
            $validated['document_number'] ?? null,
            $validated['issue_date'] ?? null,
            $validated['expiry_date'] ?? null,
        );

        return back()->with('flash', 'Document uploaded for verification.');
    }

    public function storeUnavailable(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle = $this->scoped($request, $vehicle);

        $validated = $request->validate([
            'from_at' => ['required', 'date'],
            'to_at' => ['required', 'date', 'after:from_at'],
            'type' => ['required', Rule::in(['maintenance', 'blocked', 'other'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle->unavailablePeriods()->create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('flash', 'Unavailable period added.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, VendorProfile $profile, ?Vehicle $vehicle = null): array
    {
        $validated = $request->validate([
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'name' => ['required', 'string', 'max:120'],
            'registration_number' => [
                'required', 'string', 'max:30',
                Rule::unique('vehicles', 'registration_number')->where('vendor_profile_id', $profile->id)->ignore($vehicle?->id),
            ],
            'make' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'color' => ['nullable', 'string', 'max:40'],
            'passenger_capacity' => ['required', 'integer', 'min:1', 'max:60'],
            'luggage_capacity' => ['nullable', 'integer', 'min:0', 'max:60'],
            'fuel_type' => ['nullable', Rule::in(TaxiFuelType::values())],
            'transmission' => ['nullable', Rule::in(['manual', 'automatic'])],
            'is_air_conditioned' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(VehicleStatus::values())],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['registration_number'] = strtoupper(trim($validated['registration_number']));

        return $validated;
    }
}
