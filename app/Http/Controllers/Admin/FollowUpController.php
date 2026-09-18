<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FollowUpStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadTimelineEntry;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cross-lead follow-up queue (due today / overdue / upcoming / mine).
 * Rows resolve through lead visibility so follow-ups never leak leads
 * the staffer may not see.
 */
class FollowUpController extends Controller
{
    public function __construct(protected LeadService $leads) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $visibleLeadIds = Lead::visibleTo($user)->pluck('id');

        $query = LeadFollowUp::query()
            ->whereIn('lead_id', $visibleLeadIds)
            ->with(['lead:id,reference,name,phone', 'assignee:id,name']);

        match ($request->string('filter')->toString()) {
            'overdue' => $query->overdue(),
            'upcoming' => $query->pending()->where('due_at', '>=', now()),
            'completed' => $query->where('status', FollowUpStatus::Completed->value),
            'mine' => $query->pending()->where('assigned_to', $user->id),
            default => $query->pending()->whereDate('due_at', today()),
        };

        $base = LeadFollowUp::query()->whereIn('lead_id', $visibleLeadIds);

        return Inertia::render('Admin/FollowUps/Index', [
            'followUps' => $query->orderBy('due_at')->paginate(15)->withQueryString(),
            'filter' => $request->string('filter')->toString() ?: 'due_today',
            'counts' => [
                'due_today' => (clone $base)->pending()->whereDate('due_at', today())->count(),
                'overdue' => (clone $base)->overdue()->count(),
                'upcoming' => (clone $base)->pending()->where('due_at', '>', now())->count(),
                'mine' => (clone $base)->pending()->where('assigned_to', $user->id)->count(),
            ],
        ]);
    }

    public function complete(Request $request, LeadFollowUp $followUp): RedirectResponse
    {
        $this->scoped($request, $followUp);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $followUp->update([
            'status' => FollowUpStatus::Completed->value,
            'completed_at' => now(),
            'note' => $validated['note'] ?? $followUp->note,
        ]);

        $lead = $followUp->lead;
        $lead->update(['last_contacted_at' => now()]);
        $this->leads->log($lead, LeadTimelineEntry::FOLLOW_UP_COMPLETED, $request->user(), [
            'follow_up_id' => $followUp->id,
            'type' => $followUp->type,
        ]);
        $this->leads->refreshFollowUpPointer($lead);

        return back()->with('flash', 'Follow-up marked completed.');
    }

    public function cancel(Request $request, LeadFollowUp $followUp): RedirectResponse
    {
        $this->scoped($request, $followUp);

        $followUp->update(['status' => FollowUpStatus::Cancelled->value]);
        $this->leads->refreshFollowUpPointer($followUp->lead);

        return back()->with('flash', 'Follow-up cancelled.');
    }

    protected function scoped(Request $request, LeadFollowUp $followUp): LeadFollowUp
    {
        // 404 (not 403) when the parent lead is outside visibility.
        Lead::visibleTo($request->user())->findOrFail($followUp->lead_id);

        return $followUp;
    }
}
