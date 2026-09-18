<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Enums\TaxiBookingStatus;
use App\Jobs\OperationalReminderJob;
use App\Jobs\QueueHeartbeatJob;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\LeadFollowUp;
use App\Models\Quotation;
use App\Models\TaxiBooking;
use App\Support\ModuleManager;
use App\Support\OperationsSettings;
use Illuminate\Support\Carbon;

/**
 * Scheduler-driven operational reminders (Phase 11.5D).
 *
 * The scheduler tick only SELECTs due rows and dispatches queued jobs —
 * it never sends mail or writes notifications synchronously. Each job
 * is idempotent (marker columns), so overlapping ticks and retries
 * cannot double-send.
 *
 * Quotations use exact status rules: only sent/viewed can expire or
 * be reminded; accepted/rejected/converted/superseded/expired/draft
 * are never touched.
 */
class OperationalReminders
{
    public function __construct(
        protected QuotationService $quotations,
        protected BookingPaymentService $payments,
    ) {}

    protected function remindersEnabled(): bool
    {
        return OperationsSettings::enabled('ops.reminders_enabled');
    }

    /**
     * @return array<string, int>
     */
    public function remindFollowUps(): array
    {
        if (! $this->remindersEnabled() || ! OperationsSettings::enabled('ops.followup_reminders_enabled')) {
            return ['due' => 0, 'overdue' => 0];
        }

        $now = now();
        $due = 0;
        $overdue = 0;

        // Due: recently became due (last 24h) or due within 15 minutes,
        // never reminded. One notification per follow-up.
        LeadFollowUp::pending()
            ->whereNull('reminder_sent_at')
            ->where('due_at', '<=', $now->copy()->addMinutes(15))
            ->where('due_at', '>', $now->copy()->subDay())
            ->orderBy('due_at')
            ->chunkById(200, function ($rows) use (&$due): void {
                foreach ($rows as $row) {
                    OperationalReminderJob::dispatch('followup_due', $row->id);
                    $due++;
                }
            });

        // Overdue escalation: pending past due >24h, never
        // overdue-reminded. Separate marker, separate message wording.
        LeadFollowUp::pending()
            ->whereNull('overdue_reminder_sent_at')
            ->where('due_at', '<=', $now->copy()->subDay())
            ->orderBy('due_at')
            ->chunkById(200, function ($rows) use (&$overdue): void {
                foreach ($rows as $row) {
                    OperationalReminderJob::dispatch('followup_overdue', $row->id);
                    $overdue++;
                }
            });

        return ['due' => $due, 'overdue' => $overdue];
    }

    /**
     * Mark open quotations past valid_until as expired (exact status
     * rules) and queue expiring-soon reminders.
     *
     * @return array{expired: int, reminded: int}
     */
    public function expireQuotations(): array
    {
        if (! $this->remindersEnabled() || ! OperationsSettings::enabled('ops.quotation_expiry_enabled')) {
            return ['expired' => 0, 'reminded' => 0];
        }

        $expired = 0;
        $reminded = 0;
        $today = Carbon::today();
        $windowDays = max(1, (int) (OperationsSettings::get('ops.quotation_expiry_reminder_days') ?? 3));

        Quotation::whereIn('status', [QuotationStatus::Sent->value, QuotationStatus::Viewed->value])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', $today)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$expired): void {
                foreach ($rows as $quotation) {
                    try {
                        $this->quotations->markExpired($quotation->refresh(), null);
                        $expired++;
                    } catch (\Throwable) {
                        // Status moved concurrently (accepted/converted by
                        // staff) — exact status rules win, skip silently.
                    }
                }
            });

        Quotation::whereIn('status', [QuotationStatus::Sent->value, QuotationStatus::Viewed->value])
            ->whereNotNull('valid_until')
            ->whereNull('expiry_reminder_sent_at')
            ->whereDate('valid_until', '>=', $today)
            ->whereDate('valid_until', '<=', $today->copy()->addDays($windowDays))
            ->orderBy('valid_until')
            ->chunkById(200, function ($rows) use (&$reminded): void {
                foreach ($rows as $quotation) {
                    OperationalReminderJob::dispatch('quotation_expiring', $quotation->id);
                    $reminded++;
                }
            });

        return ['expired' => $expired, 'reminded' => $reminded];
    }

    /**
     * Payment due reminders. Only when a due date is configured and an
     * outstanding balance exists; offsets come from settings.
     */
    public function remindPayments(): int
    {
        if (! $this->remindersEnabled()) {
            return 0;
        }

        $offsets = OperationsSettings::intOffsets('ops.payment_reminder_offsets');

        if ($offsets === []) {
            return 0;
        }

        $today = Carbon::today();
        $targetDates = array_map(fn (int $o): string => $today->copy()->addDays($o)->toDateString(), $offsets);
        $count = 0;

        Booking::whereNotNull('payment_due_date')
            // whereDate: date columns may carry a midnight time part
            // depending on driver — compare calendar dates, not strings.
            ->where(function ($query) use ($targetDates): void {
                foreach ($targetDates as $date) {
                    $query->orWhereDate('payment_due_date', $date);
                }
            })
            ->whereNotIn('booking_status', ['cancelled'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$count, $today): void {
                foreach ($rows as $booking) {
                    $summary = $this->payments->summary($booking);

                    if ($summary['due'] <= 0) {
                        continue;
                    }

                    // One reminder per due-date day.
                    $last = $booking->last_payment_reminder_at;

                    if ($last && Carbon::parse($last)->isSameDay($today)) {
                        continue;
                    }

                    $booking->forceFill(['last_payment_reminder_at' => now()])->save();
                    OperationalReminderJob::dispatch('payment_due', $booking->id);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Travel reminders for customers and vendors at configured offsets.
     *
     * @return array{customer: int, vendor: int, taxi_customer: int, taxi_vendor: int}
     */
    public function remindTravel(): array
    {
        if (! $this->remindersEnabled()) {
            return ['customer' => 0, 'vendor' => 0, 'taxi_customer' => 0, 'taxi_vendor' => 0];
        }

        $customerOffsets = OperationsSettings::intOffsets('ops.travel_reminder_customer_offsets');
        $vendorOffsets = OperationsSettings::intOffsets('ops.travel_reminder_vendor_offsets');
        $today = Carbon::today();
        $counts = ['customer' => 0, 'vendor' => 0, 'taxi_customer' => 0, 'taxi_vendor' => 0];

        $allOffsets = array_values(array_unique(array_merge($customerOffsets, $vendorOffsets)));

        if ($allOffsets === []) {
            return $counts;
        }

        $targetDates = array_map(fn (int $o): string => $today->copy()->addDays($o)->toDateString(), $allOffsets);

        Booking::where(function ($query) use ($targetDates): void {
            foreach ($targetDates as $date) {
                $query->orWhereDate('travel_date', $date);
            }
        })
            ->whereNotIn('booking_status', ['cancelled'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$counts, $today, $customerOffsets, $vendorOffsets): void {
                foreach ($rows as $booking) {
                    // Carbon 3 returns float diffs — cast for strict compare.
                    $daysOut = (int) $today->diffInDays(Carbon::parse($booking->travel_date)->startOfDay(), false);

                    if (in_array($daysOut, $customerOffsets, true) && ! $booking->last_travel_reminder_at) {
                        $booking->forceFill(['last_travel_reminder_at' => now()])->save();
                        OperationalReminderJob::dispatch('travel_customer', $booking->id);
                        $counts['customer']++;
                    }

                    if ($booking->vendor_profile_id && in_array($daysOut, $vendorOffsets, true) && ! $booking->last_vendor_travel_reminder_at) {
                        $booking->forceFill(['last_vendor_travel_reminder_at' => now()])->save();
                        OperationalReminderJob::dispatch('travel_vendor', $booking->id);
                        $counts['vendor']++;
                    }
                }
            });

        if (app(ModuleManager::class)->isEnabled(ModuleManager::TAXI)) {
            $eligibleStatuses = [
                TaxiBookingStatus::Confirmed->value,
                TaxiBookingStatus::DriverAssigned->value,
                TaxiBookingStatus::EnRoute->value,
                TaxiBookingStatus::Arrived->value,
                TaxiBookingStatus::PassengerOnBoard->value,
            ];

            TaxiBooking::whereIn('status', $eligibleStatuses)
                ->whereNotNull('pickup_at')
                ->where(function ($query) use ($targetDates): void {
                    foreach ($targetDates as $date) {
                        $query->orWhereDate('pickup_at', $date);
                    }
                })
                ->orderBy('id')
                ->chunkById(200, function ($rows) use (&$counts, $today, $customerOffsets, $vendorOffsets): void {
                    foreach ($rows as $booking) {
                        $pickup = Carbon::parse($booking->pickup_at)->startOfDay();
                        $daysOut = (int) $today->diffInDays($pickup, false);

                        if ($booking->customer_user_id && in_array($daysOut, $customerOffsets, true) && ! $booking->last_customer_reminder_at) {
                            $booking->forceFill(['last_customer_reminder_at' => now()])->save();
                            OperationalReminderJob::dispatch('taxi_travel_customer', $booking->id);
                            $counts['customer']++;
                            $counts['taxi_customer']++;
                        }

                        if ($booking->vendor_profile_id && in_array($daysOut, $vendorOffsets, true) && ! $booking->last_vendor_reminder_at) {
                            $booking->forceFill(['last_vendor_reminder_at' => now()])->save();
                            OperationalReminderJob::dispatch('taxi_travel_vendor', $booking->id);
                            $counts['vendor']++;
                            $counts['taxi_vendor']++;
                        }
                    }
                });
        }

        return $counts;
    }

    /**
     * Dispatch due scheduled campaigns via the existing queued
     * chunked sender. Cancelled campaigns never send.
     */
    public function dispatchDueCampaigns(): int
    {
        if (! OperationsSettings::enabled('ops.campaigns_scheduled_enabled')) {
            return 0;
        }

        $count = 0;

        Campaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->chunkById(50, function ($rows) use (&$count): void {
                foreach ($rows as $campaign) {
                    if ($campaign->refresh()->status !== 'scheduled') {
                        continue;
                    }

                    app(CampaignService::class)->sendNow($campaign->refresh());
                    $count++;
                }
            });

        return $count;
    }

    public function dispatchQueueHeartbeat(): void
    {
        QueueHeartbeatJob::dispatch();
    }

    /**
     * Daily summary line for scheduler logs / health checks.
     *
     * @return array<string, mixed>
     */
    public function runDueReminders(): array
    {
        return [
            'followups' => $this->remindFollowUps(),
            'quotations' => $this->expireQuotations(),
            'payments' => $this->remindPayments(),
            'travel' => $this->remindTravel(),
            'campaigns' => $this->dispatchDueCampaigns(),
        ];
    }
}
