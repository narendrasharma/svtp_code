<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Enquiry;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadTimelineEntry;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\CrmNotification;
use App\Support\OperationsSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM lead pipeline. Leads are pre-customer interest records: they may
 * exist with no user account, no booking, and no product behind them.
 */
class LeadService
{
    use NotifiesAdmins;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createLead(array $data, ?User $actor = null): Lead
    {
        return DB::transaction(function () use ($data, $actor): Lead {
            $lead = Lead::create([
                'reference' => app(NumberSeriesService::class)->next('lead'),
                'enquiry_id' => $data['enquiry_id'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'service_type' => $data['service_type'] ?? 'tour',
                'product_title' => $data['product_title'] ?? null,
                'destination' => $data['destination'] ?? null,
                'travel_start_date' => $data['travel_start_date'] ?? null,
                'travel_end_date' => $data['travel_end_date'] ?? null,
                'adults' => $data['adults'] ?? null,
                'children' => $data['children'] ?? null,
                'budget' => $data['budget'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => LeadStatus::New->value,
                'assigned_to' => $data['assigned_to'] ?? null,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'created_by' => $actor?->id,
                'summary' => $data['summary'] ?? null,
            ]);

            $this->log($lead, LeadTimelineEntry::CREATED, $actor, [
                'source' => $lead->source?->name,
                'service_type' => $lead->service_type,
            ]);

            if ($lead->assigned_to) {
                $this->notifyAssignee($lead, $actor);
            }

            // Manual lead creation alert is opt-in (website enquiries
            // would otherwise flood the admin audience).
            if (OperationsSettings::enabled('ops.notify_lead_created')) {
                $this->notifyAdmins(new AdminAlert('lead_created', [
                    'lead_id' => $lead->id,
                    'reference' => $lead->reference,
                    'name' => $lead->name,
                ]));
            }

            return $lead;
        });
    }

    /**
     * Compatibility bridge: every website enquiry automatically becomes
     * (or links) a CRM lead. The public enquiry forms, math CAPTCHA and
     * admin Enquiries page are untouched — the lead is the pipeline side
     * of the same interest. Idempotent per enquiry.
     */
    public function fromEnquiry(Enquiry $enquiry): Lead
    {
        $existing = Lead::where('enquiry_id', $enquiry->id)->first();

        if ($existing) {
            return $existing;
        }

        $source = LeadSource::where('slug', 'website')->first();

        return DB::transaction(function () use ($enquiry, $source): Lead {
            $enquiry->loadMissing('tourPackage:id,title');

            $summary = $enquiry->message;

            if ($enquiry->enquiry_type === 'tour_plan') {
                $bits = array_filter([
                    $enquiry->pickup_drop ? "Pickup/drop: {$enquiry->pickup_drop}" : null,
                    $enquiry->hotel_category ? "Hotel: {$enquiry->hotel_category}" : null,
                ]);
                $summary = trim(implode("\n", $bits).($summary ? "\n{$summary}" : ''));
            }

            $lead = Lead::create([
                'reference' => app(NumberSeriesService::class)->next('lead'),
                'enquiry_id' => $enquiry->id,
                'source_id' => $source?->id,
                'name' => $enquiry->full_name,
                'phone' => $enquiry->phone,
                'email' => $enquiry->email,
                'service_type' => 'tour',
                'product_title' => $enquiry->tourPackage?->title,
                'travel_start_date' => $enquiry->arrival_date,
                'travel_end_date' => $enquiry->departure_date,
                'adults' => $enquiry->adults,
                'children' => $enquiry->children,
                'status' => LeadStatus::New->value,
                'summary' => $summary !== '' ? $summary : null,
            ]);

            $this->log($lead, LeadTimelineEntry::CREATED, null, [
                'enquiry_id' => $enquiry->id,
                'enquiry_type' => $enquiry->enquiry_type,
            ]);

            return $lead;
        });
    }

    public function assign(Lead $lead, ?User $assignee, ?User $actor = null): Lead
    {
        if ($assignee !== null && ! $assignee->isAdmin()) {
            throw ValidationException::withMessages(['assigned_to' => 'Leads can only be assigned to internal staff.']);
        }

        $previous = $lead->assigned_to;
        $lead->update(['assigned_to' => $assignee?->id]);

        if ($previous !== $lead->assigned_to) {
            $this->log($lead, LeadTimelineEntry::ASSIGNED, $actor, [
                'from' => $previous,
                'to' => $lead->assigned_to,
                'to_name' => $assignee?->name,
            ]);

            if ($assignee) {
                $this->notifyAssignee($lead, $actor);
            }
        }

        return $lead->refresh();
    }

    public function changeStatus(Lead $lead, LeadStatus $to, ?User $actor = null, ?string $lostReason = null): Lead
    {
        $from = $lead->status()->value;

        if ($lead->isTerminal()) {
            throw ValidationException::withMessages(['status' => "Lead is already {$from} and cannot be moved."]);
        }

        if ($to === LeadStatus::Lost && trim((string) $lostReason) === '') {
            throw ValidationException::withMessages(['lost_reason' => 'A lost reason is required when losing a lead.']);
        }

        $lead->update([
            'status' => $to->value,
            'lost_reason' => $to === LeadStatus::Lost ? $lostReason : null,
        ]);

        $this->log($lead, LeadTimelineEntry::STATUS_CHANGED, $actor, ['from' => $from, 'to' => $to->value]);

        return $lead->refresh();
    }

    public function addNote(Lead $lead, string $body, ?User $actor = null): void
    {
        $this->log($lead, LeadTimelineEntry::NOTE_ADDED, $actor, ['note' => mb_substr(trim($body), 0, 2000)]);
        $lead->touch();
    }

    /**
     * Link the lead to a customer account (existing or freshly created).
     * Lead history is retained — conversion never deletes the lead.
     */
    public function linkCustomer(Lead $lead, User $customer, ?User $actor = null): Lead
    {
        if (! $customer->isCustomer()) {
            throw ValidationException::withMessages(['customer_user_id' => 'Leads can only be linked to customer accounts.']);
        }

        $lead->update(['customer_user_id' => $customer->id]);
        $this->log($lead, LeadTimelineEntry::CONVERTED_TO_CUSTOMER, $actor, [
            'customer_user_id' => $customer->id,
            'customer_name' => $customer->name,
        ]);

        return $lead->refresh();
    }

    public function markWon(Lead $lead, int $bookingId, ?User $actor = null): Lead
    {
        $lead->update([
            'status' => LeadStatus::Won->value,
            'converted_booking_id' => $bookingId,
            'lost_reason' => null,
        ]);
        $this->log($lead, LeadTimelineEntry::CONVERTED_TO_BOOKING, $actor, ['booking_id' => $bookingId]);

        return $lead->refresh();
    }

    /**
     * Recompute the lead's next-follow-up pointer from pending items.
     */
    public function refreshFollowUpPointer(Lead $lead): void
    {
        $next = $lead->pendingFollowUps()->orderBy('due_at')->first();

        $lead->update(['next_follow_up_at' => $next?->due_at]);
    }

    public function log(Lead $lead, string $event, ?User $actor = null, array $metadata = []): void
    {
        $lead->timeline()->create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    protected function notifyAssignee(Lead $lead, ?User $actor = null): void
    {
        $assignee = $lead->assignee;

        if (! $assignee || $actor?->id === $assignee->id) {
            return;
        }

        $assignee->notify(new CrmNotification('lead_assigned', [
            'lead_id' => $lead->id,
            'reference' => $lead->reference,
            'name' => $lead->name,
        ]));
    }
}
