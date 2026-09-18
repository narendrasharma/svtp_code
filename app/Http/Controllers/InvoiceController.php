<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\InvoiceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected InvoiceService $invoices) {}

    public function show(Booking $booking)
    {
        $this->authorize('viewInvoice', $booking);

        // Phase 9: explicitly shaped receipt — raw booking (with vendor
        // economics) never reaches customer props.
        return Inertia::render('Booking/Invoice', ['receipt' => $this->invoices->receiptData($booking)]);
    }

    public function download(Booking $booking)
    {
        $this->authorize('viewInvoice', $booking);

        return response($this->invoices->generateInvoicePdf($booking), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->invoices->invoiceFilename($booking).'"',
        ]);
    }
}
