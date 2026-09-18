<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * CRM + reservation-desk in-app notifications (Phase 11.5B).
 *
 * Minimal by design: assignment, acceptance, money-in and desk-booking
 * events only. No bulk messaging, no WhatsApp/SMS providers — those
 * belong to the Communication Center phase.
 */
class CrmNotification extends MarketplaceNotification
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
            'lead_assigned' => 'Lead assigned: '.($this->data['reference'] ?? ''),
            'follow_up_created' => 'Follow-up scheduled',
            'quotation_accepted' => 'Quotation accepted: '.($this->data['reference'] ?? ''),
            'quotation_sent' => 'New quotation: '.($this->data['reference'] ?? ''),
            'payment_recorded' => 'Payment recorded: '.($this->data['payment_reference'] ?? ''),
            'offline_booking_created' => 'Booking created: '.($this->data['reference'] ?? ''),
            'support_reply' => 'Support ticket updated: '.($this->data['reference'] ?? ''),
            'ticket_assigned' => 'Ticket assigned: '.($this->data['reference'] ?? ''),
            'admin_message' => (string) ($this->data['title'] ?? 'Message from our team'),
            'campaign_message' => (string) ($this->data['subject'] ?? 'Update'),
            'quotation_expiring' => 'Quotation expiring: '.($this->data['reference'] ?? ''),
            'payment_due' => 'Payment due: '.($this->data['reference'] ?? ''),
            'travel_reminder' => 'Upcoming trip: '.($this->data['reference'] ?? ''),
            'vendor_travel_reminder' => 'Upcoming booking: '.($this->data['reference'] ?? ''),
            'admin_digest' => 'Operations digest',
            'taxi_booking_confirmed' => 'Taxi ride confirmed: '.($this->data['reference'] ?? ''),
            'taxi_driver_assigned' => 'Driver assigned: '.($this->data['reference'] ?? ''),
            'taxi_assignment' => 'Trip assigned: '.($this->data['reference'] ?? ''),
            'taxi_travel_reminder' => 'Upcoming ride: '.($this->data['reference'] ?? ''),
            'taxi_vendor_travel_reminder' => 'Upcoming trip: '.($this->data['reference'] ?? ''),
            default => 'CRM update',
        };
    }

    public function message(): string
    {
        return match ($this->kind) {
            'lead_assigned' => 'Lead '.($this->data['reference'] ?? '').' ('.($this->data['name'] ?? 'a lead').') was assigned to you.',
            'follow_up_created' => 'Follow-up for lead '.($this->data['reference'] ?? '').' is due '.($this->data['due'] ?? 'soon').'.',
            'quotation_accepted' => 'Quotation '.$this->displayReference().' totaling ₹'.($this->data['total'] ?? '?').' was accepted.',
            'quotation_sent' => 'Quotation '.$this->displayReference().' totaling ₹'.($this->data['total'] ?? '?').' is ready to view.',
            'payment_recorded' => '₹'.($this->data['amount'] ?? '?').' received against booking '.($this->data['reference'] ?? '').' ('.($this->data['payment_reference'] ?? '').'). Outstanding ₹'.($this->data['due'] ?? '?').'.',
            'offline_booking_created' => 'Booking '.($this->data['reference'] ?? '').' totaling ₹'.($this->data['total'] ?? '?').' was created for you.',
            'support_reply' => isset($this->data['resolved']) && $this->data['resolved']
                ? 'Ticket '.($this->data['reference'] ?? '').' was '.($this->data['status'] ?? 'resolved').'.'
                : 'Our team replied to ticket '.($this->data['reference'] ?? '').' ('.($this->data['subject'] ?? '').').',
            'ticket_assigned' => 'Ticket '.($this->data['reference'] ?? '').' ('.($this->data['subject'] ?? '').') was assigned to you.',
            'admin_message' => (string) ($this->data['body'] ?? 'You have a new message from our team.'),
            'campaign_message' => (string) ($this->data['body'] ?? 'You have a new update.'),
            'quotation_expiring' => 'Quotation '.$this->displayReference().' totaling ₹'.($this->data['total'] ?? '?').' expires on '.($this->data['valid_until'] ?? 'soon').'.',
            'payment_due' => '₹'.($this->data['amount'] ?? '?').' is due for booking '.($this->data['reference'] ?? '').($this->data['due_date'] ?? null ? ' by '.$this->data['due_date'] : '').'.',
            'travel_reminder' => 'Your trip "'.($this->data['tour'] ?? 'tour').'" ('.($this->data['reference'] ?? '').') starts on '.($this->data['travel_date'] ?? 'soon').'.',
            'vendor_travel_reminder' => 'Booking '.($this->data['reference'] ?? '').' travels on '.($this->data['travel_date'] ?? 'soon').'. Please be prepared.',
            'admin_digest' => (string) ($this->data['body'] ?? 'Here is your operations summary.'),
            'taxi_booking_confirmed' => 'Your taxi ride '.($this->data['reference'] ?? '').' on '.($this->data['pickup_at'] ?? 'soon').' from '.($this->data['pickup_address'] ?? '').' is confirmed. Total ₹'.($this->data['total'] ?? '?').'.',
            'taxi_driver_assigned' => 'Driver '.($this->data['driver'] ?? 'assigned').' ('.($this->data['vehicle'] ?? 'vehicle assigned').') will pick you up on '.($this->data['pickup_at'] ?? 'soon').' for ride '.($this->data['reference'] ?? '').'.',
            'taxi_assignment' => 'You are assigned to ride '.($this->data['reference'] ?? '').' — pickup '.($this->data['pickup_at'] ?? 'soon').' at '.($this->data['pickup_address'] ?? '').'.',
            'taxi_travel_reminder' => 'Reminder: your ride '.($this->data['reference'] ?? '').' picks up on '.($this->data['pickup_at'] ?? 'soon').' from '.($this->data['pickup_address'] ?? '').'.',
            'taxi_vendor_travel_reminder' => 'Reminder: ride '.($this->data['reference'] ?? '').' picks up on '.($this->data['pickup_at'] ?? 'soon').'.',
            default => 'There is a new CRM update for you.',
        };
    }

    public function actionUrl(): ?string
    {
        return match ($this->kind) {
            'lead_assigned', 'follow_up_created' => isset($this->data['lead_id']) ? "/admin/leads/{$this->data['lead_id']}" : '/admin/leads',
            'quotation_accepted' => isset($this->data['quotation_id']) ? "/admin/quotations/{$this->data['quotation_id']}" : '/admin/quotations',
            'quotation_sent' => $this->data['public_url'] ?? (isset($this->data['quotation_id']) ? "/admin/quotations/{$this->data['quotation_id']}" : '/admin/quotations'),
            'payment_recorded', 'offline_booking_created' => isset($this->data['booking_id']) ? "/admin/bookings/{$this->data['booking_id']}" : '/admin/bookings',
            'quotation_expiring' => isset($this->data['quotation_id']) ? "/admin/quotations/{$this->data['quotation_id']}" : '/admin/quotations',
            'payment_due', 'travel_reminder' => isset($this->data['booking_id']) ? "/admin/bookings/{$this->data['booking_id']}" : '/admin/bookings',
            'vendor_travel_reminder' => isset($this->data['booking_id']) ? "/vendor/bookings/{$this->data['booking_id']}" : '/vendor/bookings',
            'taxi_booking_confirmed', 'taxi_driver_assigned', 'taxi_travel_reminder' => isset($this->data['taxi_booking_id']) ? "/admin/taxi/bookings/{$this->data['taxi_booking_id']}" : '/admin/taxi/bookings',
            'taxi_assignment', 'taxi_vendor_travel_reminder' => isset($this->data['taxi_booking_id']) ? "/vendor/taxi/bookings/{$this->data['taxi_booking_id']}" : '/vendor/taxi/bookings',
            'support_reply' => isset($this->data['ticket_id']) ? $this->supportTicketUrl() : null,
            'ticket_assigned' => isset($this->data['ticket_id']) ? '/admin/support/'.$this->data['ticket_id'] : '/admin/support',
            'admin_message', 'campaign_message' => $this->data['action_url'] ?? null,
            default => null,
        };
    }

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

    protected function displayReference(): string
    {
        return (string) ($this->data['reference'] ?? 'quotation');
    }

    protected function supportTicketUrl(): ?string
    {
        if (! isset($this->data['ticket_id'])) {
            return null;
        }

        return match ($this->data['portal'] ?? 'admin') {
            'account' => "/account/support/{$this->data['ticket_id']}",
            'vendor' => "/vendor/support/{$this->data['ticket_id']}",
            default => "/admin/support/{$this->data['ticket_id']}",
        };
    }
}
