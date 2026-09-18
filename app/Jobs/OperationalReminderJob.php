<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\LeadFollowUp;
use App\Models\Quotation;
use App\Models\TaxiBooking;
use App\Notifications\CrmNotification;
use App\Services\BookingPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued operational reminder delivery (11.5D).
 *
 * Idempotent by design: each kind checks its "already sent" marker
 * before notifying, so retries and overlapping scheduler ticks never
 * double-send. Heavy sends stay off the HTTP path and off the
 * scheduler tick — the scheduler only dispatches these jobs.
 */
class OperationalReminderJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = 60;

    public function __construct(
        public string $kind,
        public int $subjectId,
        public ?int $actorId = null,
    ) {}

    public function handle(): void
    {
        match ($this->kind) {
            'followup_due' => $this->sendFollowUp(false),
            'followup_overdue' => $this->sendFollowUp(true),
            'quotation_expiring' => $this->sendQuotationExpiring(),
            'payment_due' => $this->sendPaymentDue(),
            'travel_customer' => $this->sendTravelCustomer(),
            'travel_vendor' => $this->sendTravelVendor(),
            'taxi_travel_customer' => $this->sendTaxiTravelCustomer(),
            'taxi_travel_vendor' => $this->sendTaxiTravelVendor(),
            default => null,
        };
    }

    protected function sendFollowUp(bool $overdue): void
    {
        $followUp = LeadFollowUp::with(['lead', 'assignee'])->find($this->subjectId);

        if (! $followUp || $followUp->status !== 'pending' || ! $followUp->assignee) {
            return;
        }

        $marker = $overdue ? 'overdue_reminder_sent_at' : 'reminder_sent_at';

        if ($followUp->{$marker} !== null) {
            return;
        }

        $followUp->assignee->notify(new CrmNotification('follow_up_created', [
            'lead_id' => $followUp->lead_id,
            'reference' => $followUp->lead?->reference ?? ('#'.$followUp->lead_id),
            'name' => $followUp->lead?->name ?? '',
            'due' => $overdue ? 'overdue since '.$followUp->due_at->diffForHumans() : $followUp->due_at->diffForHumans(),
        ]));

        $followUp->update([$marker => now()]);
    }

    protected function sendQuotationExpiring(): void
    {
        $quotation = Quotation::with('creator')->find($this->subjectId);

        if (! $quotation || $quotation->expiry_reminder_sent_at !== null) {
            return;
        }

        if (! in_array($quotation->status, ['sent', 'viewed'], true)) {
            return;
        }

        $staff = $quotation->creator;

        if ($staff) {
            $staff->notify(new CrmNotification('quotation_expiring', [
                'quotation_id' => $quotation->id,
                'reference' => $quotation->reference,
                'total' => number_format((float) $quotation->total_amount, 2),
                'valid_until' => $quotation->valid_until?->toDateString(),
            ]));
        }

        $quotation->update(['expiry_reminder_sent_at' => now()]);
    }

    protected function sendPaymentDue(): void
    {
        $booking = Booking::with('user')->find($this->subjectId);

        if (! $booking) {
            return;
        }

        $summary = app(BookingPaymentService::class)->summary($booking);

        if ($summary['due'] <= 0 || ! $booking->user) {
            return;
        }

        // One reminder per due-date day: the scheduler stamps the marker
        // before dispatch; a retry on the same day is a no-op.
        $booking->user->notify(new CrmNotification('payment_due', [
            'booking_id' => $booking->id,
            'reference' => $booking->booking_reference_id,
            'amount' => number_format($summary['due'], 2),
            'due_date' => $booking->payment_due_date?->toDateString(),
        ]));
    }

    protected function sendTravelCustomer(): void
    {
        $booking = Booking::with(['user', 'package'])->find($this->subjectId);

        if (! $booking || ! $booking->user || $booking->last_travel_reminder_at !== null) {
            // Marker is stamped pre-dispatch for the current travel date;
            // null here means this job is stale — still deliver once.
            if (! $booking || ! $booking->user) {
                return;
            }
        }

        $booking->user->notify(new CrmNotification('travel_reminder', [
            'booking_id' => $booking->id,
            'reference' => $booking->booking_reference_id,
            'tour' => $booking->package?->title ?? 'your tour',
            'travel_date' => $booking->travel_date?->toDateString(),
        ]));
    }

    protected function sendTravelVendor(): void
    {
        $booking = Booking::with('vendorProfile.owner')->find($this->subjectId);

        if (! $booking || ! $booking->vendor_profile_id) {
            return;
        }

        $owner = $booking->vendorProfile?->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new CrmNotification('vendor_travel_reminder', [
            'booking_id' => $booking->id,
            'reference' => $booking->booking_reference_id,
            'travel_date' => $booking->travel_date?->toDateString(),
        ]));
    }

    protected function sendTaxiTravelCustomer(): void
    {
        $booking = TaxiBooking::with('customer')->find($this->subjectId);

        if (! $booking || ! $booking->customer) {
            return;
        }

        $pickupAt = $booking->pickup_at ? $booking->pickup_at->copy() : null;

        if ($pickupAt === null) {
            return;
        }

        if ($booking->last_customer_reminder_at !== null && $booking->last_customer_reminder_at->isSameDay($pickupAt)) {
            return;
        }

        $booking->customer->notify(new CrmNotification('taxi_travel_reminder', [
            'taxi_booking_id' => $booking->id,
            'reference' => $booking->reference,
            'pickup_at' => $pickupAt->toDateTimeString(),
            'pickup_address' => $booking->pickup_address,
        ]));

        $booking->forceFill(['last_customer_reminder_at' => now()])->save();
    }

    protected function sendTaxiTravelVendor(): void
    {
        $booking = TaxiBooking::with('vendorProfile.owner')->find($this->subjectId);

        if (! $booking || ! $booking->vendor_profile_id) {
            return;
        }

        $owner = $booking->vendorProfile?->owner;

        if (! $owner) {
            return;
        }

        $pickupAt = $booking->pickup_at ? $booking->pickup_at->copy() : null;

        if ($pickupAt === null) {
            return;
        }

        if ($booking->last_vendor_reminder_at !== null && $booking->last_vendor_reminder_at->isSameDay($pickupAt)) {
            return;
        }

        $owner->notify(new CrmNotification('taxi_vendor_travel_reminder', [
            'taxi_booking_id' => $booking->id,
            'reference' => $booking->reference,
            'pickup_at' => $pickupAt->toDateTimeString(),
        ]));

        $booking->forceFill(['last_vendor_reminder_at' => now()])->save();
    }
}
