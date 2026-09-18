<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UploadVendorDocumentRequest;
use App\Models\VendorDocument;
use App\Models\VendorVerification;
use App\Services\VendorDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class VendorDocumentController extends Controller
{
    public function __construct(
        protected VendorDocumentService $documentService
    ) {}

    public function store(UploadVendorDocumentRequest $request): RedirectResponse
    {
        $user = $request->user();

        $verification = VendorVerification::where('user_id', $user->id)
            ->latest()
            ->firstOrFail();

        // Ensure verification belongs to latest application and is in submittable state
        // Approved-but-unverified vendors must be able to continue KYC uploads
        $application = $verification->application;
        $allowedStatuses = ['pending', 'resubmission_requested'];
        $isApprovedVendorWithPendingKyc = $application && $application->status->value === 'approved' && $verification->status->value !== 'verified' && $user->isVendor();
        if ($application && ! in_array($application->status->value, $allowedStatuses, true) && ! $isApprovedVendorWithPendingKyc) {
            return back()->withErrors(['document_file' => 'Cannot upload documents for a completed application.']);
        }

        $document = $this->documentService->store(
            $verification,
            $request->string('document_type')->toString(),
            $request->file('document_file'),
            $request->input('document_number_masked')
        );

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function destroy(Request $request, VendorDocument $document): RedirectResponse
    {
        $user = $request->user();

        $verification = $document->verification;
        if (! $verification || (int) $verification->user_id !== (int) $user->id) {
            abort(403);
        }

        // Allow deletion only if pending or rejected and not verified
        if ($document->status->value === 'verified') {
            return back()->withErrors(['document' => 'Verified documents cannot be deleted.']);
        }

        // Soft delete? For now hard delete but keep audit via storage? We'll keep record but allow replacement.
        // Instead, delete file and record.
        $disk = config('vendor_kyc.disk', 'vendor_kyc');
        Storage::disk($disk)->delete($document->storage_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    public function download(Request $request, VendorDocument $document)
    {
        $user = $request->user();

        $verification = $document->verification;
        if (! $verification || (int) $verification->user_id !== (int) $user->id) {
            // Check if admin can view? This route is for vendor; admin has separate
            abort(403);
        }

        if (! Gate::allows('view', $document)) {
            abort(403);
        }

        return $this->documentService->secureDownload($document);
    }
}
