<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverEmploymentStatus;
use App\Enums\TaxiDriverDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverAvailability;
use App\Models\DriverDocument;
use App\Models\VendorProfile;
use App\Services\NumberSeriesService;
use App\Services\TaxiDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin driver management (12A.1). Drivers are vendor-owned domain
 * rows; no User account required. Document numbers stay masked.
 */
class DriverController extends Controller
{
    public function __construct(protected TaxiDocumentService $documents) {}

    public function index(Request $request): Response
    {
        $drivers = Driver::with(['vendorProfile:id,business_name', 'user:id,name'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('reference', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('availability_status'), fn ($q) => $q->where('availability_status', $request->string('availability_status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Taxi/Drivers/Index', [
            'drivers' => $drivers,
            'filters' => $request->only(['search', 'vendor_id', 'availability_status']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'availabilityStatuses' => collect(DriverAvailabilityStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Taxi/Drivers/Form', [
            'driver' => null,
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'availabilityStatuses' => collect(DriverAvailabilityStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'employmentStatuses' => collect(DriverEmploymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $driver = Driver::create([
            'reference' => app(NumberSeriesService::class)->next('taxi_driver'),
            ...$this->validated($request),
        ]);

        return redirect()->route('admin.taxi.drivers.show', $driver)->with('flash', "Driver {$driver->reference} created.");
    }

    public function show(Driver $driver): Response
    {
        $driver->load(['vendorProfile:id,business_name', 'user:id,name,email', 'documents.verifier:id,name', 'availabilities']);

        return Inertia::render('Admin/Taxi/Drivers/Show', [
            'driver' => $driver,
            'documentTypes' => collect(TaxiDriverDocumentType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'expiringDocuments' => $driver->documents()->expiringSoon()->count(),
            'activeTrips' => $driver->assignments()->open()->count(),
        ]);
    }

    public function edit(Driver $driver): Response
    {
        return Inertia::render('Admin/Taxi/Drivers/Form', [
            'driver' => $driver,
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'availabilityStatuses' => collect(DriverAvailabilityStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'employmentStatuses' => collect(DriverEmploymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validated($request));

        return back()->with('flash', 'Driver updated.');
    }

    public function storeDocument(Request $request, Driver $driver): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(TaxiDriverDocumentType::values())],
            'document_file' => ['required', 'file', 'max:5120'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ]);

        $this->documents->storeDriverDocument(
            $driver,
            $validated['document_type'],
            $request->file('document_file'),
            $validated['document_number'] ?? null,
            $validated['issue_date'] ?? null,
            $validated['expiry_date'] ?? null,
        );

        return back()->with('flash', 'Driver document uploaded for verification.');
    }

    public function verifyDocument(Request $request, DriverDocument $document): RedirectResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->documents->verify($document, $request->user(), (bool) $validated['approved'], $validated['review_note'] ?? null);

        return back()->with('flash', $validated['approved'] ? 'Document verified.' : 'Document rejected.');
    }

    public function storeAvailability(Request $request, Driver $driver): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'required_without_all:from_at,to_at'],
            'from_at' => ['nullable', 'date', 'required_without:date'],
            'to_at' => ['nullable', 'date', 'after:from_at', 'required_with:from_at'],
            'status' => ['required', Rule::in(['available', 'unavailable', 'on_leave'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $driver->availabilities()->create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('flash', 'Availability window saved.');
    }

    public function destroyAvailability(DriverAvailability $availability): RedirectResponse
    {
        $availability->delete();

        return back()->with('flash', 'Availability window removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'vendor_profile_id' => ['required', 'integer', 'exists:vendor_profiles,id'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'joining_date' => ['nullable', 'date'],
            'driver_type' => ['nullable', 'string', 'max:30'],
            'availability_status' => ['sometimes', Rule::in(DriverAvailabilityStatus::values())],
            'employment_status' => ['sometimes', Rule::in(DriverEmploymentStatus::values())],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
