<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Enums\LeadPriority;
use App\Enums\LeadStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadTimelineEntry;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\CustomerService;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRM lead pipeline (Phase 11.5B). Visibility is server-side scoped:
 * leads.view_all sees everything, everyone else sees only assigned
 * leads. Single rows are resolved through the same scope so existence
 * never leaks to unauthorized staff.
 */
class LeadController extends Controller
{
    public function __construct(protected LeadService $leads, protected CustomerService $customers) {}

    public function index(Request $request): Response
    {
        $query = Lead::visibleTo($request->user())
            ->with(['source:id,name', 'assignee:id,name'])
            ->withCount(['pendingFollowUps']);

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($q) => $q
                ->where('reference', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('email', 'like', $term));
        }

        if ($request->filled('status') && in_array($request->string('status')->toString(), LeadStatus::values(), true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority')->toString());
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->string('service_type')->toString());
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        match ($request->string('filter')->toString()) {
            'unassigned' => $query->whereNull('assigned_to'),
            'hot' => $query->where('priority', 'hot')->whereNotIn('status', ['won', 'lost']),
            'due_today' => $query->whereHas('pendingFollowUps', fn ($q) => $q->whereDate('due_at', today())),
            'overdue' => $query->whereHas('pendingFollowUps', fn ($q) => $q->where('due_at', '<', now())),
            'upcoming' => $query->whereHas('pendingFollowUps', fn ($q) => $q->where('due_at', '>=', now())),
            default => null,
        };

        $leads = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $leads,
            'filters' => $request->only(['search', 'status', 'priority', 'service_type', 'assigned_to', 'filter']),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'serviceTypes' => $this->serviceTypeOptions(),
            'staff' => $this->staffOptions(),
            'canViewAll' => (bool) $request->user()->can('leads.view_all'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Leads/Form', [
            'lead' => null,
            'sources' => $this->sourceOptions(),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'serviceTypes' => $this->serviceTypeOptions(),
            'staff' => $this->staffOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lead = $this->leads->createLead($this->validated($request), $request->user());

        return redirect()->route('admin.leads.show', $lead)->with('flash', "Lead {$lead->reference} created.");
    }

    public function show(Request $request, int $lead): Response
    {
        $model = $this->scoped($request, $lead);
        $model->load([
            'source:id,name', 'assignee:id,name', 'customer:id,name,email,phone',
            'creator:id,name', 'enquiry:id,full_name,enquiry_type',
            'convertedBooking:id,booking_reference_id',
            'quotations' => fn ($q) => $q->orderByDesc('id')->limit(10),
            'followUps.assignee:id,name',
            'followUps.creator:id,name',
            'timeline.actor:id,name',
        ]);

        return Inertia::render('Admin/Leads/Show', [
            'lead' => $model,
            'sources' => $this->sourceOptions(),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'serviceTypes' => $this->serviceTypeOptions(),
            'followUpTypes' => collect(FollowUpType::cases())->map(fn ($t): array => ['value' => $t->value, 'label' => $t->label()]),
            'followUpStatuses' => collect(FollowUpStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
            'staff' => $this->staffOptions(),
            'permissions' => [
                'update' => $request->user()->can('leads.update'),
                'assign' => $request->user()->can('leads.assign'),
                'convert' => $request->user()->can('leads.convert'),
            ],
        ]);
    }

    public function edit(Request $request, int $lead): Response
    {
        $model = $this->scoped($request, $lead);

        return Inertia::render('Admin/Leads/Form', [
            'lead' => $model->load('source:id,name'),
            'sources' => $this->sourceOptions(),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'serviceTypes' => $this->serviceTypeOptions(),
            'staff' => $this->staffOptions(),
        ]);
    }

    public function update(Request $request, int $lead): RedirectResponse
    {
        $model = $this->scoped($request, $lead);
        $model->update($this->validated($request, $model->id));

        return back()->with('flash', 'Lead updated.');
    }

    public function assign(Request $request, int $lead): RedirectResponse
    {
        $model = $this->scoped($request, $lead);
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $assignee = $validated['assigned_to'] ? User::find($validated['assigned_to']) : null;
        $this->leads->assign($model, $assignee, $request->user());

        return back()->with('flash', $assignee ? "Lead assigned to {$assignee->name}." : 'Lead unassigned.');
    }

    public function status(Request $request, int $lead): RedirectResponse
    {
        $model = $this->scoped($request, $lead);
        $validated = $request->validate([
            'status' => ['required', Rule::in(LeadStatus::values())],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->leads->changeStatus($model, LeadStatus::from($validated['status']), $request->user(), $validated['lost_reason'] ?? null);

        return back()->with('flash', 'Lead status updated.');
    }

    public function note(Request $request, int $lead): RedirectResponse
    {
        $model = $this->scoped($request, $lead);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $this->leads->addNote($model, $validated['body'], $request->user());

        return back()->with('flash', 'Note added to timeline.');
    }

    /**
     * Link an existing customer account, or quick-create one. Either way
     * the lead keeps its full history.
     */
    public function convert(Request $request, int $lead): RedirectResponse
    {
        $model = $this->scoped($request, $lead);

        if ($model->customer_user_id) {
            return back()->with('flash', 'Lead is already linked to a customer.');
        }

        $validated = $request->validate([
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required_without:customer_user_id', 'string', 'max:255'],
            'phone' => ['required_without:customer_user_id', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        if (! empty($validated['customer_user_id'])) {
            $customer = User::findOrFail($validated['customer_user_id']);
        } else {
            // Email only when explicitly provided; otherwise the customer
            // gets a placeholder identity (no mail, invite-ready later).
            $customer = $this->customers->createLightweight([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'source' => 'lead',
            ], $request->user());
        }

        $this->leads->linkCustomer($model, $customer, $request->user());

        return back()->with('flash', "Lead linked to customer {$customer->name}.");
    }

    public function storeFollowUp(Request $request, int $lead): RedirectResponse
    {
        $model = $this->scoped($request, $lead);
        $validated = $request->validate([
            'due_at' => ['required', 'date'],
            'type' => ['required', Rule::in(array_column(FollowUpType::cases(), 'value'))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignee = ! empty($validated['assigned_to']) ? User::find($validated['assigned_to']) : ($model->assigned_to ? $model->assignee : $request->user());

        if ($assignee && ! $assignee->isAdmin()) {
            return back()->withErrors(['assigned_to' => 'Follow-ups can only be assigned to internal staff.']);
        }

        $followUp = $model->followUps()->create([
            'assigned_to' => $assignee?->id,
            'due_at' => $validated['due_at'],
            'type' => $validated['type'],
            'status' => FollowUpStatus::Pending->value,
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->leads->log($model, LeadTimelineEntry::FOLLOW_UP_ADDED, $request->user(), [
            'follow_up_id' => $followUp->id,
            'due_at' => $followUp->due_at->toDateTimeString(),
            'type' => $followUp->type,
        ]);
        $this->leads->refreshFollowUpPointer($model);

        if ($assignee && $assignee->id !== $request->user()->id) {
            $assignee->notify(new CrmNotification('follow_up_created', [
                'lead_id' => $model->id,
                'reference' => $model->reference,
                'due' => $followUp->due_at->format('d M Y H:i'),
            ]));
        }

        return back()->with('flash', 'Follow-up scheduled.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'source_id' => ['nullable', 'integer', 'exists:lead_sources,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'service_type' => ['nullable', Rule::in(array_column(ServiceType::cases(), 'value'))],
            'product_title' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'travel_start_date' => ['nullable', 'date'],
            'travel_end_date' => ['nullable', 'date', 'after_or_equal:travel_start_date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:100'],
            'children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'priority' => ['nullable', Rule::in(array_column(LeadPriority::cases(), 'value'))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'next_follow_up_at' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    protected function scoped(Request $request, int $lead): Lead
    {
        return Lead::visibleTo($request->user())->findOrFail($lead);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return collect(LeadStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()])->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function priorityOptions(): array
    {
        return collect(LeadPriority::cases())->map(fn ($p): array => ['value' => $p->value, 'label' => $p->label()])->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function serviceTypeOptions(): array
    {
        return collect(ServiceType::cases())->map(fn ($t): array => ['value' => $t->value, 'label' => $t->label()])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function sourceOptions(): array
    {
        return LeadSource::active()->get(['id', 'name'])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function staffOptions(): array
    {
        return User::where('role', 'admin')->orderBy('name')->get(['id', 'name'])->all();
    }
}
