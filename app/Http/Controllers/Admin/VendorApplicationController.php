<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorApplicationStatus;
use App\Enums\VendorVerificationStatus;
use App\Events\KycDecided;
use App\Http\Controllers\Controller;
use App\Models\VendorApplication;
use App\Models\VendorVerification;
use App\Services\VendorApprovalService;
use App\Services\VendorKycRequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorApplicationController extends Controller
{
    public function __construct(
        protected VendorApprovalService $approvalService,
        protected VendorKycRequirementService $kycService,
    ) {}

    public function index(Request $request): Response
    {
        $applications = VendorApplication::with(['user:id,name,email', 'verification.documents', 'reviewer:id,name'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('business_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('kyc_status'), function ($q) use ($request) {
                $kyc = $request->string('kyc_status')->toString();
                $q->whereHas('verification', fn ($vv) => $vv->where('status', $kyc));
            })
            ->when($request->filled('country'), fn ($q) => $q->where('country_code', $request->string('country')->toString()))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Enhance with kyc summary for index table
        $applications->getCollection()->transform(function ($app) {
            $verification = $app->verification;
            if ($verification) {
                $summary = $this->kycService->summarize($verification);
                $app->setAttribute('kyc_summary', $summary);
                $app->setAttribute('kyc_status', $verification->status->value);
            } else {
                $app->setAttribute('kyc_summary', null);
                $app->setAttribute('kyc_status', 'not_started');
            }

            return $app;
        });

        return Inertia::render('Admin/VendorApplications/Index', [
            'applications' => $applications,
            'filters' => $request->only(['search', 'status', 'kyc_status', 'country', 'entity_type']),
            'statuses' => collect(VendorApplicationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'kycStatuses' => collect(VendorVerificationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(VendorApplication $vendorApplication): Response
    {
        $vendorApplication->load(['user:id,name,email,phone,role', 'reviewer:id,name', 'verification.documents.reviewer:id,name', 'verification.documents.verification']);

        $verification = $vendorApplication->verification;
        if (! $verification) {
            // Create placeholder if missing (legacy)
            $verification = VendorVerification::create([
                'user_id' => $vendorApplication->user_id,
                'vendor_application_id' => $vendorApplication->id,
                'status' => VendorVerificationStatus::NotStarted,
                'country_code' => $vendorApplication->country_code,
                'entity_type' => $vendorApplication->entity_type,
            ]);
            $verification->load('documents');
            $vendorApplication->setRelation('verification', $verification);
        }

        $summary = $this->kycService->summarize($verification);
        $requirements = $this->kycService->requirements($verification->country_code, $verification->entity_type ?? $vendorApplication->entity_type);

        // Map documents with safe fields (no storage_path)
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
                'reviewed_by' => $doc->reviewer?->name,
            ];
        });

        return Inertia::render('Admin/VendorApplications/Show', [
            'application' => $vendorApplication,
            'verification' => [
                'id' => $verification->id,
                'status' => $verification->status instanceof \BackedEnum ? $verification->status->value : $verification->status,
                'country_code' => $verification->country_code,
                'entity_type' => $verification->entity_type,
                'submitted_at' => $verification->submitted_at,
                'reviewed_at' => $verification->reviewed_at,
                'verified_at' => $verification->verified_at,
                'rejection_reason' => $verification->rejection_reason,
                'review_note' => $verification->review_note,
            ],
            'documents' => $documents,
            'summary' => $summary,
            'requirements' => $requirements,
            'catalog' => $this->kycService->catalog(),
            'canApprove' => true,
            'requireKycBeforeApproval' => $this->kycService->requireKycBeforeApproval(),
            'kycIncomplete' => ! $summary['is_complete'],
        ]);
    }

    public function approve(Request $request, VendorApplication $vendorApplication): RedirectResponse
    {
        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'allow_incomplete_kyc' => ['nullable', 'boolean'],
        ]);

        // Only admin may override KYC — ensure middleware already protects, but validate flag
        $allowIncomplete = $request->boolean('allow_incomplete_kyc', true);

        try {
            $this->approvalService->approve($vendorApplication, $request->user()->id, $request->input('admin_note'), $allowIncomplete);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('success', 'Vendor application approved.');
    }

    public function reject(Request $request, VendorApplication $vendorApplication): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->approvalService->reject($vendorApplication, $request->user()->id, $request->string('rejection_reason')->toString(), $request->input('admin_note'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['rejection_reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Vendor application rejected.');
    }

    public function requestResubmission(Request $request, VendorApplication $vendorApplication): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->approvalService->requestResubmission($vendorApplication, $request->user()->id, $request->string('rejection_reason')->toString(), $request->input('admin_note'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['rejection_reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Resubmission requested.');
    }

    /**
     * Mark verification as verified (admin review).
     */
    public function verifyKyc(Request $request, VendorApplication $vendorApplication): RedirectResponse
    {
        $verification = $vendorApplication->verification;
        if (! $verification) {
            return back()->withErrors(['verification' => 'No verification record.']);
        }

        $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->kycService->isVerificationComplete($verification)) {
            return back()->withErrors(['verification' => 'Cannot verify — required documents missing or not verified.']);
        }

        $verification->update([
            'status' => VendorVerificationStatus::Verified,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'verified_at' => now(),
            'review_note' => $request->input('review_note'),
            'rejection_reason' => null,
        ]);

        KycDecided::dispatch($verification->refresh(), 'verified');

        return back()->with('success', 'KYC marked as verified.');
    }
}
