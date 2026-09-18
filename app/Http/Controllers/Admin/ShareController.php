<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Quotation;
use App\Services\BookingPaymentService;
use App\Services\Comms\ShareService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Document sharing hub: email a branded message with a secure link, or
 * mint a manual WhatsApp share URL (logged, never auto-sent). SMS is
 * refused loudly while no provider is configured.
 */
class ShareController extends Controller
{
    public function __construct(
        protected ShareService $share,
        protected BookingPaymentService $payments,
        protected InvoiceService $invoices,
    ) {}

    public function quotation(Request $request, Quotation $quotation): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
            'to_email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
            'to_phone' => ['required_if:channel,whatsapp', 'nullable', 'string', 'max:30'],
        ]);

        $publicUrl = $this->share->quotationPublicUrl($quotation);
        $data = [
            'customer_name' => $quotation->customer?->name ?? $quotation->lead?->name ?? '',
            'quotation_reference' => $quotation->displayReference(),
            'amount' => (string) $quotation->total_amount,
            'valid_until' => $quotation->valid_until?->toDateString() ?? '',
            'secure_url' => $publicUrl,
            'site_name' => config('app.name'),
        ];

        if ($validated['channel'] === 'email') {
            $template = CommunicationTemplate::where('key', 'quotation_sent')->first();

            Mail::html(
                nl2br(e((string) $this->share->templates()->renderText($template?->email_body, $data))),
                function ($message) use ($validated, $template, $data): void {
                    $message->to($validated['to_email'])
                        ->subject((string) $this->share->templates()->renderText($template?->email_subject, $data));
                }
            );

            $this->share->logManualShare($quotation, 'email', $request->user(), CommunicationLog::maskEmail($validated['to_email']), [
                'template_key' => 'quotation_sent',
                'event' => 'document_shared',
                'recipient_type' => 'customer',
            ]);

            return response()->json(['sent' => true]);
        }

        $message = $this->share->renderShareMessage('quotation_sent', 'whatsapp', $data);
        $url = $this->share->whatsappShareUrl($validated['to_phone'], (string) $message);

        if ($url === null) {
            return response()->json(['message' => 'That phone number cannot receive a WhatsApp share link.'], 422);
        }

        $this->share->logManualShare($quotation, 'manual_share', $request->user(), CommunicationLog::maskPhone($validated['to_phone']), [
            'template_key' => 'quotation_sent',
            'event' => 'document_shared',
            'provider' => 'whatsapp-manual',
            'recipient_type' => 'customer',
        ]);

        return response()->json(['whatsapp_url' => $url]);
    }

    public function invoice(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
            'to_email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
            'to_phone' => ['required_if:channel,whatsapp', 'nullable', 'string', 'max:30'],
        ]);

        $url = $this->share->invoiceShareUrl($booking);
        $summary = $this->payments->summary($booking);
        $data = [
            'customer_name' => $booking->customer_name ?? '',
            'booking_reference' => $booking->booking_reference_id,
            'travel_date' => $booking->travel_date?->toDateString() ?? '',
            'amount' => (string) $booking->total_amount,
            'amount_due' => number_format($summary['due'], 2),
            'secure_url' => $url,
            'site_name' => config('app.name'),
        ];

        if ($validated['channel'] === 'email') {
            $template = CommunicationTemplate::where('key', 'invoice_ready')->first();

            Mail::html(
                nl2br(e((string) $this->share->templates()->renderText($template?->email_body, $data))),
                function ($message) use ($validated, $template, $data): void {
                    $message->to($validated['to_email'])
                        ->subject((string) $this->share->templates()->renderText($template?->email_subject, $data));
                }
            );

            $this->share->logManualShare($booking, 'email', $request->user(), CommunicationLog::maskEmail($validated['to_email']), [
                'template_key' => 'invoice_ready',
                'event' => 'document_shared',
                'recipient_type' => 'customer',
            ]);

            return response()->json(['sent' => true, 'secure_url' => $url]);
        }

        $message = $this->share->renderShareMessage('invoice_ready', 'whatsapp', $data);
        $waUrl = $this->share->whatsappShareUrl($validated['to_phone'], (string) $message);

        if ($waUrl === null) {
            return response()->json(['message' => 'That phone number cannot receive a WhatsApp share link.'], 422);
        }

        $this->share->logManualShare($booking, 'manual_share', $request->user(), CommunicationLog::maskPhone($validated['to_phone']), [
            'template_key' => 'invoice_ready',
            'event' => 'document_shared',
            'provider' => 'whatsapp-manual',
            'recipient_type' => 'customer',
        ]);

        return response()->json(['whatsapp_url' => $waUrl, 'secure_url' => $url]);
    }

    public function receipt(Request $request, Booking $booking, BookingPayment $payment): JsonResponse
    {
        abort_unless((int) $payment->booking_id === (int) $booking->id, 404);

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
            'to_email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
            'to_phone' => ['required_if:channel,whatsapp', 'nullable', 'string', 'max:30'],
        ]);

        $url = $this->share->receiptShareUrl($payment);
        $summary = $this->payments->summary($booking->refresh());
        $data = [
            'customer_name' => $booking->customer_name ?? '',
            'booking_reference' => $booking->booking_reference_id,
            'payment_reference' => $payment->reference,
            'amount' => (string) $payment->amount,
            'amount_due' => number_format($summary['due'], 2),
            'secure_url' => $url,
            'site_name' => config('app.name'),
        ];

        if ($validated['channel'] === 'email') {
            $template = CommunicationTemplate::where('key', 'payment_received')->first();

            Mail::html(
                nl2br(e((string) $this->share->templates()->renderText($template?->email_body, $data))),
                function ($message) use ($validated, $template, $data): void {
                    $message->to($validated['to_email'])
                        ->subject((string) $this->share->templates()->renderText($template?->email_subject, $data));
                }
            );

            $this->share->logManualShare($payment, 'email', $request->user(), CommunicationLog::maskEmail($validated['to_email']), [
                'template_key' => 'payment_received',
                'event' => 'document_shared',
                'recipient_type' => 'customer',
            ]);

            return response()->json(['sent' => true, 'secure_url' => $url]);
        }

        $message = $this->share->renderShareMessage('payment_received', 'whatsapp', $data);
        $waUrl = $this->share->whatsappShareUrl($validated['to_phone'], (string) $message);

        if ($waUrl === null) {
            return response()->json(['message' => 'That phone number cannot receive a WhatsApp share link.'], 422);
        }

        $this->share->logManualShare($payment, 'manual_share', $request->user(), CommunicationLog::maskPhone($validated['to_phone']), [
            'template_key' => 'payment_received',
            'event' => 'document_shared',
            'provider' => 'whatsapp-manual',
            'recipient_type' => 'customer',
        ]);

        return response()->json(['whatsapp_url' => $waUrl, 'secure_url' => $url]);
    }
}
