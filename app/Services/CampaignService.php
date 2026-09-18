<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Jobs\SendCampaign;
use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lightweight newsletter/bulk-messaging foundation. Transactional
 * notifications are a separate path and never consult marketing
 * preferences — campaigns always do.
 *
 * Delivery topology: Send Now creates one delivery row per recipient
 * (unique key = no duplicates, re-runnable) and dispatches a single
 * SendCampaign job that works the queue in chunks. With QUEUE_CONNECTION
 * =sync the job runs inline in the request; production needs a worker
 * (`queue:work`, 11.5D) for large audiences — the campaign stays in
 * `sending` with per-recipient rows until the job finishes, so nothing
 * is silently lost either way.
 */
class CampaignService
{
    public function resolveRecipients(Campaign $campaign)
    {
        $query = User::query()->orderBy('id');

        $filter = $campaign->audience_filter ?? [];

        match ($campaign->audience_type) {
            Campaign::AUDIENCE_ALL_CUSTOMERS => $query->where('role', 'customer'),
            Campaign::AUDIENCE_ALL_VENDORS => $query->where('role', 'vendor'),
            Campaign::AUDIENCE_ALL_USERS => $query->whereIn('role', ['customer', 'vendor']),
            Campaign::AUDIENCE_SELECTED => $query->whereIn('id', $filter['user_ids'] ?? [-1]),
            default => $query->whereRaw('1 = 0'),
        };

        if (($filter['with_bookings'] ?? false) === true) {
            $query->whereHas('bookings');
        }

        if (($filter['verified_vendors'] ?? false) === true) {
            $query->whereHas('vendorProfile', fn ($q) => $q->where('is_active', true));
        }

        return $query;
    }

    public function audienceCount(Campaign $campaign): int
    {
        return (clone $this->resolveRecipients($campaign))->count();
    }

    /**
     * Marketing opt-in gate per channel. Email honors opt-out; in-app is
     * account messaging and always delivers.
     */
    public function channelAllowed(User $user, string $channel): bool
    {
        return match ($channel) {
            CommunicationChannel::Email->value => (bool) $user->marketing_email_opt_in,
            CommunicationChannel::Sms->value => (bool) $user->marketing_sms_opt_in,
            CommunicationChannel::Whatsapp->value => (bool) $user->marketing_whatsapp_opt_in,
            default => true,
        };
    }

    public function sendNow(Campaign $campaign, ?User $actor = null): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value], true)) {
            throw ValidationException::withMessages(['campaign' => 'Only draft or scheduled campaigns can be sent.']);
        }

        if (! in_array($campaign->channel, Campaign::sendableChannels(), true)) {
            throw ValidationException::withMessages(['channel' => 'This channel needs a configured provider before it can send.']);
        }

        return DB::transaction(function () use ($campaign, $actor): Campaign {
            $campaign->update(['status' => CampaignStatus::Sending->value, 'scheduled_at' => null]);

            $this->resolveRecipients($campaign->refresh())
                ->select('id')
                ->chunkById(200, function ($users) use ($campaign): void {
                    foreach ($users as $user) {
                        CampaignDelivery::firstOrCreate(
                            ['campaign_id' => $campaign->id, 'user_id' => $user->id, 'channel' => $campaign->channel],
                            ['status' => CommunicationStatus::Pending->value]
                        );
                    }
                });

            SendCampaign::dispatch($campaign->id, $actor?->id);

            return $campaign->refresh();
        });
    }

    public function cancel(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value, CampaignStatus::Sending->value], true)) {
            throw ValidationException::withMessages(['campaign' => 'Only unsent campaigns can be cancelled.']);
        }

        $campaign->update(['status' => CampaignStatus::Cancelled->value]);

        return $campaign->refresh();
    }

    public function logDelivery(Campaign $campaign, User $user, string $status, ?string $error = null, ?User $actor = null): void
    {
        CommunicationLog::create([
            'recipient_user_id' => $user->id,
            'recipient_type' => $user->role,
            'channel' => $campaign->channel,
            'event' => 'campaign_sent',
            'related_type' => Campaign::class,
            'related_id' => $campaign->id,
            'destination_masked' => $campaign->channel === CommunicationChannel::Email->value
                ? CommunicationLog::maskEmail($user->email)
                : null,
            'status' => $status,
            'provider' => $campaign->channel === CommunicationChannel::Email->value ? 'mail' : 'database',
            'error' => $error !== null ? mb_substr($error, 0, 500) : null,
            'sent_at' => $status === CommunicationStatus::Sent->value ? now() : null,
            'failed_at' => $status === CommunicationStatus::Failed->value ? now() : null,
            'created_by' => $actor?->id ?? $campaign->created_by,
            'metadata' => ['campaign_reference' => $campaign->reference],
        ]);
    }
}
