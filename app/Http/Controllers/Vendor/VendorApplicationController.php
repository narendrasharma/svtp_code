<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\EntityType;
use App\Enums\VendorApplicationStatus;
use App\Enums\VendorDocumentType;
use App\Enums\VendorVerificationStatus;
use App\Events\VendorApplicationSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorApplicationRequest;
use App\Models\VendorApplication;
use App\Models\VendorVerification;
use App\Services\VendorKycRequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorApplicationController extends Controller
{
    public function __construct(
        protected VendorKycRequirementService $kycService
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // If already vendor, check if KYC still pending — then show status for uploads
        if ($user->isVendor()) {
            $latestApp = VendorApplication::where('user_id', $user->id)->latest()->first();
            $latestVer = $latestApp ? VendorVerification::where('vendor_application_id', $latestApp->id)->latest()->first() : null;
            if ($latestVer && $latestVer->status !== VendorVerificationStatus::Verified) {
                return redirect()->route('vendor.application.show');
            }

            return redirect()->route('vendor.dashboard');
        }

        // If has pending application, show status instead
        $pending = VendorApplication::where('user_id', $user->id)
            ->whereIn('status', [VendorApplicationStatus::Pending, VendorApplicationStatus::ResubmissionRequested])
            ->latest()
            ->first();

        if ($pending) {
            return redirect()->route('vendor.application.show');
        }

        // Check for rejected but not yet resubmitted? Allow new application
        $latestRejected = VendorApplication::where('user_id', $user->id)
            ->where('status', VendorApplicationStatus::Rejected)
            ->latest()
            ->first();

        return Inertia::render('Vendor/Apply', [
            'entityTypes' => EntityType::options(),
            'documentCatalog' => VendorDocumentType::options(),
            'requirements' => config('vendor_kyc.requirements'),
            'consentText' => config('vendor_kyc.consent_text'),
            'consentVersion' => config('vendor_kyc.consent_policy_version'),
            'latestApplication' => $latestRejected,
        ]);
    }

    public function store(StoreVendorApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isVendor()) {
            return redirect()->route('vendor.dashboard')->with('error', 'You are already a vendor.');
        }

        $existing = VendorApplication::where('user_id', $user->id)
            ->whereIn('status', [VendorApplicationStatus::Pending, VendorApplicationStatus::ResubmissionRequested])
            ->exists();

        if ($existing) {
            return back()->withErrors(['business_name' => 'You already have a pending vendor application.'])->withInput();
        }

        // Do not allow role manipulation via request — role is server-assigned only.
        $validated = $request->validated();

        $application = VendorApplication::create([
            'user_id' => $user->id,
            'status' => VendorApplicationStatus::Pending,
            'business_name' => $validated['business_name'],
            'entity_type' => $validated['entity_type'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'country_code' => strtoupper($validated['country_code']),
            'postcode' => $validated['postcode'] ?? null,
            'website' => $validated['website'] ?? null,
            'business_description' => $validated['business_description'] ?? null,
            'consent_accepted_at' => now(),
            'consent_policy_version' => config('vendor_kyc.consent_policy_version', 'v1'),
        ]);

        // Create verification record
        VendorVerification::create([
            'user_id' => $user->id,
            'vendor_application_id' => $application->id,
            'status' => VendorVerificationStatus::Pending,
            'country_code' => strtoupper($validated['country_code']),
            'entity_type' => $validated['entity_type'],
            'submitted_at' => now(),
        ]);

        VendorApplicationSubmitted::dispatch($application);

        return redirect()->route('vendor.application.show')->with('success', 'Vendor application submitted successfully.');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $application = VendorApplication::where('user_id', $user->id)
            ->latest()
            ->first();

        if (! $application) {
            if ($user->isVendor()) {
                return redirect()->route('vendor.dashboard');
            }

            return redirect()->route('vendor.application.create');
        }

        $verification = VendorVerification::where('user_id', $user->id)
            ->where('vendor_application_id', $application->id)
            ->with(['documents'])
            ->first();

        // If no verification yet but application exists, create pending one for older records
        if (! $verification) {
            $verification = VendorVerification::create([
                'user_id' => $user->id,
                'vendor_application_id' => $application->id,
                'status' => VendorVerificationStatus::NotStarted,
                'country_code' => $application->country_code,
                'entity_type' => $application->entity_type,
            ]);
            $verification->load('documents');
        }

        // Approved vendor with verified KYC → dashboard; otherwise show status (allows uploads for pending KYC)
        if ($user->isVendor() && $verification->status === VendorVerificationStatus::Verified) {
            return redirect()->route('vendor.dashboard');
        }

        $requirements = $this->kycService->requirements(
            $verification->country_code,
            $verification->entity_type ?? $application->entity_type
        );

        $summary = $this->kycService->summarize($verification);

        // Build detailed doc status for UI
        $documentsByType = $verification->documents->groupBy(fn ($d) => $d->document_type instanceof \BackedEnum ? $d->document_type->value : $d->document_type);

        $catalog = $this->kycService->catalog();

        // Hide sensitive numbers: only expose masked
        $documents = $verification->documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'document_type' => $doc->document_type instanceof \BackedEnum ? $doc->document_type->value : $doc->document_type,
                'label' => $doc->document_type instanceof \BackedEnum ? $doc->document_type->label() : $doc->document_type,
                'status' => $doc->status instanceof \BackedEnum ? $doc->status->value : $doc->status,
                'original_filename' => $doc->original_filename,
                'mime_type' => $doc->mime_type,
                'file_size' => $doc->file_size,
                'document_number_masked' => $doc->document_number_masked,
                'rejection_reason' => $doc->rejection_reason,
                'created_at' => $doc->created_at,
                'reviewed_at' => $doc->reviewed_at,
            ];
        });

        return Inertia::render('Vendor/ApplicationStatus', [
            'application' => $application,
            'verification' => [
                'id' => $verification->id,
                'status' => $verification->status instanceof \BackedEnum ? $verification->status->value : $verification->status,
                'country_code' => $verification->country_code,
                'entity_type' => $verification->entity_type,
                'submitted_at' => $verification->submitted_at,
                'verified_at' => $verification->verified_at,
                'rejection_reason' => $verification->rejection_reason,
            ],
            'documents' => $documents,
            'requirements' => $requirements,
            'summary' => $summary,
            'catalog' => $catalog,
            'documentTypes' => VendorDocumentType::options(),
            'entityTypes' => EntityType::options(),
        ]);
    }

    /**
     * Allow resubmission when status is resubmission_requested or rejected.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $application = VendorApplication::where('user_id', $user->id)
            ->whereIn('status', [VendorApplicationStatus::ResubmissionRequested, VendorApplicationStatus::Rejected])
            ->latest()
            ->firstOrFail();

        return Inertia::render('Vendor/Resubmit', [
            'application' => $application,
            'entityTypes' => EntityType::options(),
        ]);
    }

    public function update(StoreVendorApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();

        $application = VendorApplication::where('user_id', $user->id)
            ->whereIn('status', [VendorApplicationStatus::ResubmissionRequested, VendorApplicationStatus::Rejected])
            ->latest()
            ->firstOrFail();

        $validated = $request->validated();

        $application->update([
            'business_name' => $validated['business_name'],
            'entity_type' => $validated['entity_type'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'country_code' => strtoupper($validated['country_code']),
            'postcode' => $validated['postcode'] ?? null,
            'website' => $validated['website'] ?? null,
            'business_description' => $validated['business_description'] ?? null,
            'status' => VendorApplicationStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'consent_accepted_at' => now(),
            'consent_policy_version' => config('vendor_kyc.consent_policy_version', 'v1'),
        ]);

        $verification = $application->verification;
        if ($verification) {
            $verification->update([
                'status' => VendorVerificationStatus::Pending,
                'country_code' => strtoupper($validated['country_code']),
                'entity_type' => $validated['entity_type'],
                'submitted_at' => now(),
                'rejection_reason' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'verified_at' => null,
            ]);
        }

        return redirect()->route('vendor.application.show')->with('success', 'Application resubmitted successfully.');
    }
}
