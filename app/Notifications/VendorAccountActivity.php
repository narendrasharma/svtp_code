<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Vendor account/marketplace notices (database + mail).
 *
 * Kind-driven templates keep text in one place; listeners only pick the
 * kind and pass safe display data (names, masked destinations, amounts).
 * Never pass secrets, document paths or tokens in $data.
 */
class VendorAccountActivity extends MarketplaceNotification
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
            'application_submitted' => 'Application received',
            'application_approved' => 'Vendor application approved',
            'application_rejected' => 'Vendor application update',
            'application_resubmission' => 'Application needs resubmission',
            'kyc_verified' => 'KYC verified',
            'kyc_rejected' => 'KYC update',
            'kyc_resubmission' => 'KYC needs resubmission',
            'tour_approved' => "Tour approved: {$this->tour()}",
            'tour_changes_requested' => "Changes requested: {$this->tour()}",
            'tour_rejected' => "Tour update: {$this->tour()}",
            'payout_verified' => 'Payout account verified',
            'payout_rejected' => 'Payout account update',
            'withdrawal_requested' => 'Withdrawal request received',
            'withdrawal_approved' => 'Withdrawal approved',
            'withdrawal_rejected' => 'Withdrawal update',
            'withdrawal_paid' => 'Withdrawal paid',
            'withdrawal_cancelled' => 'Withdrawal cancelled',
            default => 'Account update',
        };
    }

    public function message(): string
    {
        return match ($this->kind) {
            'application_submitted' => 'Your vendor application was received. Our team will review it shortly.',
            'application_approved' => 'Welcome aboard! Your vendor application is approved.',
            'application_rejected' => 'Your vendor application was not approved. Reason: '.($this->data['reason'] ?? 'not specified'),
            'application_resubmission' => 'Your application needs resubmission. Reason: '.($this->data['reason'] ?? 'not specified'),
            'kyc_verified' => 'Your KYC verification is complete. You can now use payouts once a payout account is verified.',
            'kyc_rejected' => 'Your KYC verification needs attention. Reason: '.($this->data['reason'] ?? 'not specified'),
            'kyc_resubmission' => 'Please resubmit your KYC documents. Reason: '.($this->data['reason'] ?? 'not specified'),
            'tour_approved' => "Your tour {$this->tour()} is approved and visible for booking.",
            'tour_changes_requested' => "Please update your tour {$this->tour()}: ".($this->data['reason'] ?? 'see review notes'),
            'tour_rejected' => "Your tour {$this->tour()} was not approved. Reason: ".($this->data['reason'] ?? 'not specified'),
            'payout_verified' => 'Your payout destination ('.($this->data['destination'] ?? 'on file').') is verified. You can now request withdrawals.',
            'payout_rejected' => 'Your payout details need attention. Reason: '.($this->data['reason'] ?? 'not specified'),
            'withdrawal_requested' => 'Withdrawal request of ₹'.($this->data['amount'] ?? '?').' received. Funds are held until review.',
            'withdrawal_approved' => 'Withdrawal of ₹'.($this->data['amount'] ?? '?').' is approved. Payout follows shortly.',
            'withdrawal_rejected' => 'Withdrawal of ₹'.($this->data['amount'] ?? '?').' was not approved. Held funds were released. Reason: '.($this->data['reason'] ?? 'not specified'),
            'withdrawal_paid' => 'Withdrawal of ₹'.($this->data['amount'] ?? '?').' has been paid'.(isset($this->data['reference']) ? " (ref {$this->data['reference']})" : '').'.',
            'withdrawal_cancelled' => 'Withdrawal of ₹'.($this->data['amount'] ?? '?').' was cancelled. Held funds were released.',
            default => 'There is an update on your vendor account.',
        };
    }

    public function actionUrl(): ?string
    {
        return match ($this->kind) {
            'application_submitted', 'application_approved', 'application_rejected', 'application_resubmission' => '/vendor/application',
            'kyc_verified', 'kyc_rejected', 'kyc_resubmission' => '/vendor/application',
            'tour_approved', 'tour_changes_requested', 'tour_rejected' => '/vendor/tours',
            'payout_verified', 'payout_rejected' => '/vendor/finance',
            'withdrawal_requested', 'withdrawal_approved', 'withdrawal_rejected', 'withdrawal_paid', 'withdrawal_cancelled' => '/vendor/finance',
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
            'action_text' => $this->actionUrl() ? 'Open vendor portal' : null,
            'action_url' => $this->actionUrl(),
        ]);
    }

    protected function tour(): string
    {
        return (string) ($this->data['tour'] ?? 'your tour');
    }
}
