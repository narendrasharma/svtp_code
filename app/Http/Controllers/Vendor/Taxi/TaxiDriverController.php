<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverEmploymentStatus;
use App\Enums\TaxiDriverDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverAvailability;
use App\Models\VendorProfile;
use App\Services\NumberSeriesService;
use App\Services\TaxiDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor drivers: own drivers only, no User account required.
 */
class TaxiDriverController extends Controller
{
    public function __construct(protected TaxiDocumentService $documents) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scoped(Request $request, Driver $driver): Driver
    {
        abort_unless((int) $driver->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $driver;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $drivers = Driver::with('user:id,name')
            ->where('vendor_profile_id', $profile->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('reference', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('availability_status'), fn ($q) => $q->where('availability_status', $request->string('availability_status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Vendor/Taxi/Drivers/Index', [
            'drivers' => $drivers,
            'filters' => $request->only(['search', 'availability_status']),
            'availabilityStatuses' => collect(DriverAvailabilityStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->profile($request);

        return Inertia::render('Vendor/Taxi/Drivers/Form', [
            'driver' => null,
            'availabilityStatuses' => collect(DriverAvailabilityStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'employmentStatuses' => collect(DriverEmploymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $this->profile($request);

        $driver = Driver::create([
            'reference' => app(NumberSeriesService::class)->next('taxi_driver'),
            'vendor_profile_id' => $profile->id,
            ...$this->validated($request),
        ]);

        return redirect()->route('vendor.taxi.drivers.show', $driver)->with('flash', "Driver {$driver->reference} added — no login required.");
    }

    public function show(Request $request, Driver $driver): Response
    {
        $driver = $this->scoped($request, $driver);
        $driver->load(['user:id,name,email', 'documents', 'availabilities']);

        return Inertia::render('Vendor/Taxi/Drivers/Show', [
            'driver' => $driver,
            'documentTypes' => collect(TaxiDriverDocumentType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function edit(Request $request, Driver $driver): Response
    {
        $driver = $this->scoped($request, $driver);

        return Inertia::render('Vendor/Taxi/Drivers/Form', [
            'driver' => $driver,
            'availabilityStatuses' => collect(DriverAvailabilityStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'employmentStatuses' => collect(DriverEmploymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $driver = $this->scoped($request, $driver);
        $driver->update($this->validated($request));

        return back()->with('flash', 'Driver updated.');
    }

    public function storeDocument(Request $request, Driver $driver): RedirectResponse
    {
        $driver = $this->scoped($request, $driver);

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

        return back()->with('flash', 'Document uploaded for verification.');
    }

    public function storeAvailability(Request $request, Driver $driver): RedirectResponse
    {
        $driver = $this->scoped($request, $driver);

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

    public function destroyAvailability(Request $request, DriverAvailability $availability): RedirectResponse
    {
        $this->scoped($request, $availability->driver);
        $availability->delete();

        return back()->with('flash', 'Availability window removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
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
