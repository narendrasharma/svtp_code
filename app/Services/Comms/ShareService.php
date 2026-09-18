<?php

namespace App\Services\Comms;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Central document/share hub. No wa.me URL is ever hardcoded in Vue —
 * every share goes through here so message shape, phone normalization
 * and logging stay in one audited place.
 */
class ShareService
{
    public function __construct(protected TemplateService $templates) {}

    public function templates(): TemplateService
    {
        return $this->templates;
    }

    public function smsProvider(): SmsProviderInterface
    {
        $class = config('communications.sms_provider');

        if (is_string($class) && class_exists($class)) {
            $provider = app($class);

            if ($provider instanceof SmsProviderInterface && $provider->isConfigured()) {
                return $provider;
            }
        }

        return new NullSmsProvider;
    }

    public function whatsappProvider(): WhatsAppProviderInterface
    {
        $configured = config('communications.whatsapp_provider', 'manual');

        if (is_string($configured) && $configured !== 'manual' && class_exists($configured)) {
            $provider = app($configured);

            if ($provider instanceof WhatsAppProviderInterface && $provider->isConfigured()) {
                return $provider;
            }
        }

        return app(ManualWhatsAppProvider::class);
    }

    /**
     * Digits-only phone. 10-digit local numbers get the configured
     * country code; anything longer is used verbatim. Null when unusable.
     */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = preg_replace('/\D/', '', (string) config('communications.whatsapp_country_code', '91')).$digits;
        }

        return $digits;
    }

    public function whatsappShareUrl(?string $phone, string $message): ?string
    {
        $digits = $this->normalizePhone($phone);

        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    public function quotationPublicUrl(Quotation $quotation): string
    {
        return route('quotations.public', $quotation->public_token);
    }

    /**
     * Signed mutation links for the public quotation page. Signature +
     * expiry enforced by the `signed` middleware; the service layer
     * re-validates state (expiry, superseded, idempotency).
     *
     * @return array{accept_url: string, reject_url: string}
     */
    public function quotationDecisionUrls(Quotation $quotation): array
    {
        return [
            'accept_url' => URL::temporarySignedRoute('quotations.public.accept', now()->addDays(30), ['token' => $quotation->public_token]),
            'reject_url' => URL::temporarySignedRoute('quotations.public.reject', now()->addDays(30), ['token' => $quotation->public_token]),
        ];
    }

    public function invoiceShareUrl(Booking $booking): string
    {
        return URL::temporarySignedRoute('share.invoice', now()->addDays(7), ['booking' => $booking->id]);
    }

    public function receiptShareUrl(BookingPayment $payment): string
    {
        return URL::temporarySignedRoute('share.receipt', now()->addDays(7), ['payment' => $payment->id]);
    }

    /**
     * Render a template channel for a document share. Customer-safe
     * values only — never internal notes, commissions or tokens beyond
     * the intended secure URL.
     *
     * @param  array<string, string|int|float|null>  $data
     */
    public function renderShareMessage(string $templateKey, string $channel, array $data): ?string
    {
        $template = CommunicationTemplate::where('key', $templateKey)->first();

        if (! $template || ! $template->is_active) {
            return null;
        }

        $body = match ($channel) {
            'email' => $template->email_body,
            'sms' => $template->sms_body,
            'whatsapp' => $template->whatsapp_body,
            'in_app' => $template->in_app_body,
            default => null,
        };

        return $this->templates->renderText($body, $data);
    }

    /**
     * Record a staff-driven manual share (wa.me opened, link copied).
     */
    public function logManualShare(object $related, string $channel, ?User $actor, ?string $destinationMasked, array $meta = []): CommunicationLog
    {
        return CommunicationLog::create([
            'recipient_user_id' => $meta['recipient_user_id'] ?? null,
            'recipient_type' => $meta['recipient_type'] ?? 'customer',
            'channel' => $channel,
            'template_key' => $meta['template_key'] ?? null,
            'event' => $meta['event'] ?? 'manual_share',
            'related_type' => $related::class,
            'related_id' => $related->id,
            'destination_masked' => $destinationMasked,
            'status' => 'sent',
            'provider' => $meta['provider'] ?? 'manual',
            'sent_at' => now(),
            'created_by' => $actor?->id,
            'metadata' => $meta === [] ? null : $meta,
        ]);
    }
}
