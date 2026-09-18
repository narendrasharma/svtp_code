<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorDocumentStatus;
use App\Enums\VendorVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\VendorDocument;
use App\Services\VendorDocumentService;
use Illuminate\Http\Request;

class VendorDocumentController extends Controller
{
    public function __construct(
        protected VendorDocumentService $documentService
    ) {}

    public function download(Request $request, VendorDocument $vendorDocument)
    {
        // Policy check: admin only, but also ensure ability
        if (! $request->user()->isAdmin()) {
            abort(403);
        }

        abort_unless($request->user()->can('view', $vendorDocument), 403);

        return $this->documentService->secureDownload($vendorDocument);
    }

    public function verify(Request $request, VendorDocument $vendorDocument)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $vendorDocument->update([
            'status' => VendorDocumentStatus::Verified,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // If all required docs now verified, optionally auto-promote verification to under_review? Leave manual.
        // We can check if verification should be moved to pending? No.

        return back()->with('success', 'Document verified.');
    }

    public function reject(Request $request, VendorDocument $vendorDocument)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $vendorDocument->update([
            'status' => VendorDocumentStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->string('rejection_reason')->toString(),
        ]);

        // Mark verification as needs_resubmission if it was under review?
        $verification = $vendorDocument->verification;
        if ($verification && $verification->status === VendorVerificationStatus::Verified) {
            $verification->update([
                'status' => VendorVerificationStatus::NeedsResubmission,
                'rejection_reason' => 'Document rejected: '.$vendorDocument->document_type->value,
            ]);
        }

        return back()->with('success', 'Document rejected.');
    }
}
