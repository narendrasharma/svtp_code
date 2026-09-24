<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Operational admin alerts (database + mail).
 *
 * Deliberately narrow: only vendor applications, tours awaiting moderation
 * and withdrawal requests. Everything else would flood the admin audience.
 */
class AdminAlert extends MarketplaceNotification
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $kind,
        public array $data = [],
    ) {}

    protected function mailCategory(): string
    {
        return 'marketplace';
    }

    public function title(): string
    {
        return match ($this->kind) {
            'application_submitted' => 'New vendor application',
            'tour_submitted' => 'Tour awaiting moderation',
            'withdrawal_requested' => 'New withdrawal request',
            'customer_registered' => 'New customer registered',
            'lead_created' => 'New lead created',
            'booking_created' => 'New booking received',
            'cancellation_requested' => 'Cancellation requested',
            'quotation_accepted' => 'Quotation accepted',
            'kyc_submitted' => 'KYC document submitted',
            'support_ticket_opened' => 'New support ticket',
            'support_ticket_high_priority' => 'High-priority support ticket',
            'support_ticket_replied' => 'Support ticket reply',
            'campaign_failed' => 'Campaign failed',
            default => 'Operational alert',
        };
    }

    public function message(): string
    {
        return match ($this->kind) {
            'application_submitted' => 'A new vendor application was submitted by '.($this->data['applicant'] ?? 'a customer').'.',
            'tour_submitted' => 'Tour "'.($this->data['tour'] ?? 'untitled').'" was submitted for moderation.',
            'withdrawal_requested' => 'A withdrawal of ₹'.($this->data['amount'] ?? '?').' was requested by '.($this->data['vendor'] ?? 'a vendor').'.',
            'customer_registered' => ($this->data['name'] ?? 'A customer').' ('.($this->data['email'] ?? 'no email').') just registered.',
            'lead_created' => 'Lead '.($this->data['reference'] ?? '').' ('.($this->data['name'] ?? 'a prospect').') was created.',
            'booking_created' => 'Booking '.($this->data['reference'] ?? '').' totaling ₹'.($this->data['total'] ?? '?').' was received.',
            'cancellation_requested' => 'Cancellation requested for booking '.($this->data['reference'] ?? '').' by '.($this->data['requester'] ?? 'a customer').'.',
            'quotation_accepted' => 'Quotation '.($this->data['reference'] ?? '').' totaling ₹'.($this->data['total'] ?? '?').' was accepted.',
            'kyc_submitted' => ($this->data['vendor'] ?? 'A vendor').' submitted a KYC document for review.',
            'support_ticket_opened' => 'Ticket '.($this->data['reference'] ?? '').' opened by '.($this->data['requester'] ?? 'a user').': '.($this->data['subject'] ?? ''),
            'support_ticket_high_priority' => 'High-priority ticket '.($this->data['reference'] ?? '').' from '.($this->data['requester'] ?? 'a user').': '.($this->data['subject'] ?? ''),
            'support_ticket_replied' => ($this->data['requester'] ?? 'A user').' replied on ticket '.($this->data['reference'] ?? '').'.',
            'campaign_failed' => 'Campaign "'.($this->data['name'] ?? 'unnamed').'" failed — all deliveries errored.',
            default => 'There is a new operational item to review.',
        };
    }

    public function actionUrl(): ?string
    {
        return match ($this->kind) {
            'application_submitted' => isset($this->data['application_id']) ? "/admin/vendor-applications/{$this->data['application_id']}" : '/admin/vendor-applications',
            'tour_submitted' => isset($this->data['tour_id']) ? "/admin/packages/{$this->data['tour_id']}" : '/admin/packages',
            'withdrawal_requested' => isset($this->data['withdrawal_id']) ? "/admin/withdrawals/{$this->data['withdrawal_id']}" : '/admin/withdrawals',
            'customer_registered' => isset($this->data['user_id']) ? "/admin/users/{$this->data['user_id']}" : '/admin/users',
            'lead_created' => isset($this->data['lead_id']) ? "/admin/leads/{$this->data['lead_id']}" : '/admin/leads',
            'booking_created' => isset($this->data['booking_id']) ? "/admin/tour/bookings/{$this->data['booking_id']}" : '/admin/tour/bookings',
            'cancellation_requested' => isset($this->data['booking_id']) ? "/admin/tour/bookings/{$this->data['booking_id']}" : '/admin/tour/bookings',
            'quotation_accepted' => isset($this->data['quotation_id']) ? "/admin/quotations/{$this->data['quotation_id']}" : '/admin/quotations',
            'kyc_submitted' => '/admin/vendor-applications',
            'campaign_failed' => isset($this->data['campaign_id']) ? "/admin/campaigns/{$this->data['campaign_id']}" : '/admin/campaigns',
            'support_ticket_opened', 'support_ticket_high_priority', 'support_ticket_replied' => isset($this->data['ticket_id']) ? "/admin/support/{$this->data['ticket_id']}" : '/admin/support',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title(),
            'message' => $this->message(),
            'action_url' => $this->actionUrl(),
            'meta' => $this->data,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->buildMail([
            'subject' => $this->title(),
            'greeting' => 'Hello,',
            'lines' => [$this->message()],
            'action_text' => $this->actionUrl() ? 'Review now' : null,
            'action_url' => $this->actionUrl(),
        ]);
    }
}
