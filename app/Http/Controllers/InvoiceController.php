<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\InvoiceService;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoices) {}

    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        return Inertia::render('Booking/Invoice', ['booking' => $booking->load('package')]);
    }

    public function download(Booking $booking)
    {
        $this->authorize('view', $booking);

        return response($this->invoices->generateInvoicePdf($booking), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->invoices->invoiceFilename($booking) . '"',
        ]);
    }
}
