<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\SupportTicket;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorDocument;
use App\Models\VendorWithdrawalRequest;
use App\Notifications\CrmNotification;
use App\Support\OperationsSettings;

/**
 * Lightweight optional admin digest (11.5D).
 *
 * Off by default. Frequency: off|daily|weekly. The scheduler calls
 * maybeSendDaily()/maybeSendWeekly(); each admin (role=admin) gets one
 * in-app digest summarizing operational queues. Never intrusive, never
 * a report builder — counts with deep links only.
 */
class AdminDigestService
{
    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'new_leads_today' => Lead::whereDate('created_at', today())->count(),
            'overdue_followups' => LeadFollowUp::overdue()->count(),
            'followups_due_today' => LeadFollowUp::dueToday()->count(),
            'new_bookings_today' => Booking::whereDate('created_at', today())->count(),
            'pending_vendor_applications' => VendorApplication::where('status', 'pending')->count(),
            'pending_kyc' => VendorDocument::where('status', 'pending')->count(),
            'tours_awaiting_moderation' => TourPackage::where('moderation_status', 'pending_review')->count(),
            'pending_cancellations' => BookingCancellationRequest::where('status', 'pending')->count(),
            'pending_withdrawals' => VendorWithdrawalRequest::where('status', 'pending')->count(),
            'open_support_tickets' => SupportTicket::whereIn('status', ['open', 'pending_staff'])->count(),
            'failed_jobs' => app(SystemHealthService::class)->failedJobs(),
        ];
    }

    public function sendDaily(): int
    {
        return $this->send('daily');
    }

    public function sendWeekly(): int
    {
        return $this->send('weekly');
    }

    protected function send(string $period): int
    {
        $counts = $this->counts();
        $lines = [];

        foreach ($counts as $key => $value) {
            $lines[] = $this->label($key).': '.$value;
        }

        $sent = 0;

        foreach (User::where('role', 'admin')->cursor() as $admin) {
            $admin->notify(new CrmNotification('admin_digest', [
                'body' => ucfirst($period).' operations summary — '.implode('; ', $lines).'.',
                'period' => $period,
                'counts' => $counts,
                'action_url' => '/admin/dashboard',
            ]));
            $sent++;
        }

        return $sent;
    }

    public function maybeSendDaily(): int
    {
        if (OperationsSettings::get('ops.admin_digest_frequency') !== 'daily') {
            return 0;
        }

        return $this->sendDaily();
    }

    public function maybeSendWeekly(): int
    {
        if (OperationsSettings::get('ops.admin_digest_frequency') !== 'weekly') {
            return 0;
        }

        // Weekly digest goes out Monday mornings (scheduler gates the day).
        if (! now()->isMonday()) {
            return 0;
        }

        return $this->sendWeekly();
    }

    protected function label(string $key): string
    {
        return match ($key) {
            'new_leads_today' => 'New leads today',
            'overdue_followups' => 'Overdue follow-ups',
            'followups_due_today' => 'Follow-ups due today',
            'new_bookings_today' => 'New bookings today',
            'pending_vendor_applications' => 'Pending vendor applications',
            'pending_kyc' => 'Pending KYC reviews',
            'tours_awaiting_moderation' => 'Tours awaiting moderation',
            'pending_cancellations' => 'Pending cancellation requests',
            'pending_withdrawals' => 'Pending withdrawals',
            'open_support_tickets' => 'Open support tickets',
            'failed_jobs' => 'Failed jobs',
            default => $key,
        };
    }
}
