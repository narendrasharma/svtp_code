<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorDocument;
use App\Services\VendorDocumentService;
use Illuminate\Http\Request;

class VendorDocumentDownloadController extends Controller
{
    public function __construct(
        protected VendorDocumentService $service
    ) {}

    public function __invoke(Request $request, VendorDocument $vendorDocument)
    {
        // Authorize via policy
        abort_unless($request->user()->can('view', $vendorDocument), 403);

        return $this->service->secureDownload($vendorDocument);
    }
}
