<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\TaxiFuelType;
use App\Enums\TaxiVehicleDocumentType;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleType;
use App\Models\VehicleUnavailablePeriod;
use App\Models\VendorProfile;
use App\Services\NumberSeriesService;
use App\Services\TaxiDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin fleet management (12A.1): all vendors' vehicles, document
 * inspection/verification, unavailable periods. No raw document
 * numbers or file contents in lists.
 */
class VehicleController extends Controller
{
    public function __construct(protected TaxiDocumentService $documents) {}

    public function index(Request $request): Response
    {
        $vehicles = Vehicle::with(['vendorProfile:id,business_name', 'vehicleType:id,name'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('reference', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('registration_number', 'like', $term));
            })
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('vehicle_type_id'), fn ($q) => $q->where('vehicle_type_id', $request->integer('vehicle_type_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Taxi/Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => $request->only(['search', 'vendor_id', 'vehicle_type_id', 'status']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name']),
            'statuses' => collect(VehicleStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Taxi/Vehicles/Form', [
            'vehicle' => null,
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vehicle = Vehicle::create([
            'reference' => app(NumberSeriesService::class)->next('taxi_vehicle'),
            ...$this->validated($request),
        ]);

        return redirect()->route('admin.taxi.vehicles.show', $vehicle)->with('flash', "Vehicle {$vehicle->reference} created.");
    }

    public function show(Vehicle $vehicle): Response
    {
        $vehicle->load(['vendorProfile:id,business_name', 'vehicleType:id,name', 'documents.verifier:id,name', 'unavailablePeriods']);

        return Inertia::render('Admin/Taxi/Vehicles/Show', [
            'vehicle' => $vehicle,
            'documentTypes' => collect(TaxiVehicleDocumentType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'expiringDocuments' => $vehicle->documents()->expiringSoon()->count(),
        ]);
    }

    public function edit(Vehicle $vehicle): Response
    {
        return Inertia::render('Admin/Taxi/Vehicles/Form', [
            'vehicle' => $vehicle,
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name', 'passenger_capacity']),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($this->validated($request, $vehicle));

        return back()->with('flash', 'Vehicle updated.');
    }

    public function storeDocument(Request $request, Vehicle $vehicle): RedirectResponse
    {
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

        return back()->with('flash', 'Vehicle document uploaded for verification.');
    }

    public function verifyDocument(Request $request, VehicleDocument $document): RedirectResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->documents->verify($document, $request->user(), (bool) $validated['approved'], $validated['review_note'] ?? null);

        return back()->with('flash', $validated['approved'] ? 'Document verified.' : 'Document rejected.');
    }

    public function storeUnavailable(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'from_at' => ['required', 'date'],
            'to_at' => ['required', 'date', 'after:from_at'],
            'type' => ['required', Rule::in(['maintenance', 'blocked', 'other'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle->unavailablePeriods()->create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('flash', 'Unavailable period added.');
    }

    public function destroyUnavailable(Request $request, VehicleUnavailablePeriod $period): RedirectResponse
    {
        $period->delete();

        return back()->with('flash', 'Unavailable period removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $validated = $request->validate([
            'vendor_profile_id' => ['required', 'integer', 'exists:vendor_profiles,id'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'name' => ['required', 'string', 'max:120'],
            'registration_number' => [
                'required', 'string', 'max:30',
                Rule::unique('vehicles', 'registration_number')
                    ->where('vendor_profile_id', $request->integer('vendor_profile_id'))
                    ->ignore($vehicle?->id),
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
