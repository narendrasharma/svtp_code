<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoiceService
{
    public function generateInvoicePdf(Booking $booking)
    {
        $qrSvg = QrCode::size(180)->generate($booking->qr_code_string);

        $pdf = Pdf::loadView('invoices.booking', [
            'booking' => $booking->load('package', 'user'),
            'qrSvg' => $qrSvg,
        ]);

        return $pdf->output();
    }

    public function invoiceFilename(Booking $booking): string
    {
        return "invoice-{$booking->booking_reference_id}.pdf";
    }
}
