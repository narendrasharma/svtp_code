<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\NumberSeriesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Newsletter/bulk-messaging foundation. Drafts send immediately (Send
 * Now) or at a chosen time (Schedule); the scheduler dispatches due
 * campaigns through the same queued, idempotent sender. Delivery rows
 * make every send duplicate-safe.
 */
class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Campaigns/Index', [
            'campaigns' => Campaign::with('creator:id,name')->withCount([
                'deliveries',
                'deliveries as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'deliveries as failed_count' => fn ($q) => $q->where('status', 'failed'),
                'deliveries as skipped_count' => fn ($q) => $q->where('status', 'skipped'),
            ])->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => null,
            'audienceTypes' => Campaign::audienceTypes(),
            'channels' => Campaign::sendableChannels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = Campaign::create([
            'reference' => app(NumberSeriesService::class)->next('campaign'),
            ...$this->validated($request),
            'status' => CampaignStatus::Draft->value,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.campaigns.show', $campaign)->with('flash', "Campaign {$campaign->reference} drafted.");
    }

    public function show(Campaign $campaign): Response
    {
        $campaign->load('creator:id,name');

        return Inertia::render('Admin/Campaigns/Show', [
            'campaign' => $campaign,
            'audienceCount' => $this->campaigns->audienceCount($campaign),
            'deliveries' => $campaign->deliveries()->with('user:id,name,email')->latest()->paginate(20),
            'canSend' => in_array($campaign->status, [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value], true),
            // Scheduling UX: drafts may be scheduled; the app timezone is
            // authoritative for display, the backend for validation.
            'canSchedule' => $campaign->status === CampaignStatus::Draft->value,
            'timezone' => (string) config('app.timezone', 'UTC'),
        ]);
    }

    public function edit(Campaign $campaign): Response
    {
        abort_unless($campaign->status === CampaignStatus::Draft->value, 403, 'Only drafts can be edited.');

        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => $campaign,
            'audienceTypes' => Campaign::audienceTypes(),
            'channels' => Campaign::sendableChannels(),
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === CampaignStatus::Draft->value, 403, 'Only drafts can be edited.');

        $campaign->update($this->validated($request));

        return back()->with('flash', 'Campaign draft updated.');
    }

    public function send(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->campaigns->sendNow($campaign, $request->user());

        return back()->with('flash', 'Campaign send started. Deliveries are tracked per recipient below.');
    }

    /**
     * Schedule a draft for automatic dispatch (11.5D). The scheduler
     * picks it up at scheduled_at; cancelling before then prevents
     * all sends. Audience resolves at send time, consent at delivery.
     */
    public function schedule(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === CampaignStatus::Draft->value, 403, 'Only drafts can be scheduled.');

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $campaign->update([
            'status' => CampaignStatus::Scheduled->value,
            'scheduled_at' => $validated['scheduled_at'],
        ]);

        return back()->with('flash', 'Campaign scheduled. The scheduler will dispatch it automatically; cancel anytime before then.');
    }

    public function cancel(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->campaigns->cancel($campaign);

        return back()->with('flash', 'Campaign cancelled.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'channel' => ['required', Rule::in(Campaign::sendableChannels())],
            'audience_type' => ['required', Rule::in(Campaign::audienceTypes())],
            'audience_filter.user_ids' => ['nullable', 'array', 'max:200'],
            'audience_filter.user_ids.*' => ['integer', 'exists:users,id'],
            'audience_filter.with_bookings' => ['sometimes', 'boolean'],
            'audience_filter.verified_vendors' => ['sometimes', 'boolean'],
        ]);

        if (($validated['audience_type'] ?? null) === Campaign::AUDIENCE_SELECTED && empty($validated['audience_filter']['user_ids'] ?? [])) {
            abort(422, 'Select at least one user for a selected-users audience.');
        }

        // Guard the selected list against staff/admin accounts — bulk
        // marketing goes to customers and vendors only.
        if (! empty($validated['audience_filter']['user_ids'] ?? [])) {
            $validated['audience_filter']['user_ids'] = User::whereIn('id', $validated['audience_filter']['user_ids'])
                ->whereIn('role', ['customer', 'vendor'])
                ->pluck('id')
                ->all();

            if ($validated['audience_filter']['user_ids'] === []) {
                abort(422, 'Selected audience contains no customers or vendors.');
            }
        }

        return $validated;
    }
}
