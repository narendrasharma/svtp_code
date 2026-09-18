<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoiceService
{
    public function __construct(protected BookingRefundService $refunds) {}

    public function generateInvoicePdf(Booking $booking)
    {
        $qrSvg = QrCode::size(180)->generate($booking->qr_code_string);

        $pdf = Pdf::loadView('invoices.booking', [
            'booking' => $booking->load('package', 'user'),
            'receipt' => $this->receiptData($booking),
            'qrSvg' => $qrSvg,
        ]);

        return $pdf->output();
    }

    public function invoiceFilename(Booking $booking): string
    {
        return "invoice-{$booking->booking_reference_id}.pdf";
    }

    /**
     * Customer-safe receipt shaped from immutable snapshots (Phase 9).
     *
     * Refund totals derive from refund records without touching the
     * snapshot. Vendor earnings and platform commission are never included
     * — the shape itself enforces that boundary.
     *
     * @return array<string, mixed>
     */
    public function receiptData(Booking $booking): array
    {
        $booking->loadMissing('package', 'refunds', 'bookingAddons');

        $refunded = $this->refunds->processedRefundTotal($booking);
        $gross = VendorLedgerService::toDecimal((string) ($booking->gross_amount ?? $booking->total_amount));
        $money = fn ($value): string => VendorLedgerService::toDecimal((string) $value);

        return [
            'site' => [
                'name' => Setting::getValue('site_name', config('app.name')),
                'tagline' => Setting::getValue('site_tagline'),
                'phone' => Setting::getValue('primary_phone'),
                'email' => Setting::getValue('contact_email'),
                'address' => Setting::getValue('office_address'),
            ],
            'booking' => [
                'id' => $booking->id,
                'booking_reference_id' => $booking->booking_reference_id,
                'booking_status' => $booking->booking_status->value,
                'payment_status' => $booking->payment_status->value,
                'created_at' => $booking->created_at?->toDateTimeString(),
                'travel_date' => $booking->travel_date?->toDateString(),
                'total_adults' => $booking->total_adults,
                'total_children' => $booking->total_children,
                'currency' => $booking->currency,
            ],
            'customer' => [
                'name' => $booking->customer_name ?? $booking->user?->name ?? 'Guest',
                'email' => $booking->customer_email ?? $booking->user?->email,
                'phone' => $booking->customer_phone ?? $booking->user?->phone,
                'country' => $booking->country,
                'pickup_address' => $booking->pickup_address,
            ],
            'tour' => $booking->package ? [
                'title' => $booking->package->title,
                'slug' => $booking->package->slug,
                'duration_days' => $booking->package->duration_days,
                'duration_nights' => $booking->package->duration_nights,
            ] : null,
            'pricing' => [
                'base_price' => $money($booking->base_price),
                'addons_total' => $money($booking->addons_total ?? 0),
                'subtotal' => $money($booking->subtotal),
                'discount_amount' => $money($booking->discount_amount),
                'coupon_code' => $booking->coupon_code,
                'tax_amount' => $money($booking->tax_amount),
                'total_amount' => $money($booking->total_amount),
                'gross_amount' => $gross,
            ],
            'addons' => $booking->bookingAddons->map(fn ($line): array => [
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price' => $money($line->unit_price),
                'total_amount' => $money($line->total_amount),
            ])->values()->all(),
            'refunds' => [
                'total' => $refunded,
                'net' => VendorLedgerService::fromPaise(
                    VendorLedgerService::toPaise($gross) - VendorLedgerService::toPaise($refunded)
                ),
                'history' => $booking->refunds->map(fn ($refund) => [
                    'amount' => $refund->amount,
                    'reason' => $refund->reason,
                    'processed_at' => $refund->processed_at?->toDateTimeString(),
                ])->values()->all(),
            ],
            'qr_code_string' => $booking->qr_code_string,
        ];
    }
}
