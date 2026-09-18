<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Setting;
use App\Services\BookingPaymentService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

/**
 * Secure document links for sharing. Reached only through temporary
 * signed URLs minted by staff (ShareService) — no login, signature +
 * expiry enforced by the `signed` middleware. Renders the exact same
 * shapes as the authenticated views, minus any staff-only context.
 */
class SharedDocumentController extends Controller
{
    public function __construct(
        protected InvoiceService $invoices,
        protected BookingPaymentService $payments,
    ) {}

    public function invoice(Request $request, Booking $booking)
    {
        return response(
            $this->invoices->generateInvoicePdf($booking),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->invoices->invoiceFilename($booking).'"',
            ]
        );
    }

    public function receipt(Request $request, BookingPayment $payment)
    {
        $payment->loadMissing('booking');

        return response()->view('booking-payments.receipt', [
            'receipt' => $this->payments->receiptData($payment),
            'site' => [
                'name' => Setting::getValue('site_name', config('app.name')),
                'phone' => Setting::getValue('primary_phone'),
                'email' => Setting::getValue('contact_email'),
                'address' => Setting::getValue('office_address'),
            ],
        ]);
    }
}
