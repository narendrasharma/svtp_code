<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\CrmNotification;
use App\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Chunked campaign delivery. Re-runnable and duplicate-safe: delivery
 * rows are pre-created with a unique key, so a retry only picks up
 * pending rows. Marketing opt-out is re-checked at send time.
 */
class SendCampaign implements ShouldQueue
{
    use NotifiesAdmins, Queueable;

    public function __construct(
        public int $campaignId,
        public ?int $actorId = null,
    ) {}

    public function handle(CampaignService $campaigns): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (! $campaign || $campaign->status !== CampaignStatus::Sending->value) {
            return;
        }

        $actor = $this->actorId ? User::find($this->actorId) : null;

        $campaign->deliveries()
            ->where('status', CommunicationStatus::Pending->value)
            ->orderBy('id')
            ->chunkById(100, function ($deliveries) use ($campaign, $campaigns, $actor): void {
                foreach ($deliveries as $delivery) {
                    $this->deliverOne($campaign, $delivery, $campaigns, $actor);

                    // A cancelled campaign stops between chunks.
                    if ($campaign->refresh()->status !== CampaignStatus::Sending->value) {
                        return;
                    }
                }
            });

        if ($campaign->refresh()->status === CampaignStatus::Sending->value) {
            $failed = $campaign->deliveries()->where('status', CommunicationStatus::Failed->value)->count();
            $total = $campaign->deliveries()->count();
            $allFailed = $failed > 0 && $failed === $total;

            $campaign->update([
                'status' => $allFailed ? CampaignStatus::Failed->value : CampaignStatus::Completed->value,
                'sent_at' => now(),
            ]);

            if ($allFailed) {
                $this->notifyAdmins(new AdminAlert('campaign_failed', [
                    'campaign_id' => $campaign->id,
                    'name' => $campaign->name,
                ]));
            }
        }
    }

    protected function deliverOne(Campaign $campaign, CampaignDelivery $delivery, CampaignService $campaigns, ?User $actor): void
    {
        $user = $delivery->user;

        if (! $user) {
            $delivery->update(['status' => CommunicationStatus::Skipped->value]);

            return;
        }

        if (! $campaigns->channelAllowed($user, $campaign->channel)) {
            $delivery->update(['status' => CommunicationStatus::Skipped->value]);
            $campaigns->logDelivery($campaign, $user, CommunicationStatus::Skipped->value, 'Recipient opted out.', $actor);

            return;
        }

        try {
            if ($campaign->channel === CommunicationChannel::Email->value) {
                $unsubscribe = URL::signedRoute('unsubscribe.show', ['user' => $user->id]);

                Mail::raw($campaign->content."\n\n---\nUnsubscribe from marketing emails: {$unsubscribe}", function ($message) use ($campaign, $user): void {
                    $message->to($user->email)->subject($campaign->subject);
                });
            } else {
                $user->notify(new CrmNotification('campaign_message', [
                    'subject' => $campaign->subject,
                    'body' => mb_substr($campaign->content, 0, 2000),
                    'campaign_id' => $campaign->id,
                    'reference' => $campaign->reference,
                ]));
            }

            $delivery->update(['status' => CommunicationStatus::Sent->value, 'sent_at' => now(), 'error' => null]);
            $campaigns->logDelivery($campaign, $user, CommunicationStatus::Sent->value, null, $actor);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => CommunicationStatus::Failed->value,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            $campaigns->logDelivery($campaign, $user, CommunicationStatus::Failed->value, $e->getMessage(), $actor);
        }
    }
}
