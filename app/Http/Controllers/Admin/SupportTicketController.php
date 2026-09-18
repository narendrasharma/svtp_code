<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Staff support desk. All reads go through visibility scope (assigned
 * vs all); internal notes and internal attachments never serialize to
 * non-staff audiences because portals load their own shapes.
 */
class SupportTicketController extends Controller
{
    public function __construct(protected SupportService $support) {}

    public function index(Request $request): Response
    {
        $query = SupportTicket::visibleTo($request->user())
            ->with(['category:id,name', 'requester:id,name,role', 'assignee:id,name']);

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($q) => $q
                ->where('reference', 'like', $term)
                ->orWhere('subject', 'like', $term)
                ->orWhereHas('requester', fn ($r) => $r->where('name', 'like', $term)));
        }

        if ($request->filled('status') && in_array($request->string('status')->toString(), TicketStatus::values(), true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority')->toString());
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->filled('requester_type') && in_array($request->string('requester_type')->toString(), ['customer', 'vendor', 'admin'], true)) {
            $query->whereHas('requester', fn ($r) => $r->where('role', $request->string('requester_type')->toString()));
        }

        match ($request->string('filter')->toString()) {
            'unassigned' => $query->whereNull('assigned_to'),
            'stale' => $query->stale(),
            'mine' => $query->where('assigned_to', $request->user()->id),
            default => null,
        };

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'priority', 'category_id', 'assigned_to', 'requester_type', 'filter']),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'categories' => SupportCategory::active()->get(['id', 'name']),
            'staff' => User::where('role', 'admin')->orderBy('name')->get(['id', 'name']),
            'canViewAll' => (bool) $request->user()->can('support.view_all'),
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $ticket = $this->scoped($request, $ticket);

        $ticket->load([
            'category:id,name',
            'requester:id,name,email,phone,role',
            'vendorProfile:id,business_name',
            'assignee:id,name',
            'booking:id,booking_reference_id,customer_name,travel_date,total_amount,booking_status,payment_status',
            'lead:id,reference,name',
            'quotation:id,reference,revision_number,total_amount,status',
            'messages.author:id,name,role',
            'messages.attachments',
        ]);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $ticket,
            'staff' => User::where('role', 'admin')->orderBy('name')->get(['id', 'name']),
            'permissions' => [
                'reply' => $request->user()->can('support.reply'),
                'assign' => $request->user()->can('support.assign'),
                'close' => $request->user()->can('support.close'),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket = $this->scoped($request, $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $this->support->reply($ticket, $request->user(), $validated['body'], $request->file('attachments', []));

        return back()->with('flash', 'Reply sent to requester.');
    }

    public function note(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket = $this->scoped($request, $ticket);

        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $this->support->internalNote($ticket, $request->user(), $validated['body']);

        return back()->with('flash', 'Internal note saved (requester cannot see it).');
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket = $this->scoped($request, $ticket);

        $validated = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id']]);

        $assignee = $validated['assigned_to'] ? User::find($validated['assigned_to']) : null;
        $this->support->assign($ticket, $assignee, $request->user());

        return back()->with('flash', $assignee ? "Ticket assigned to {$assignee->name}." : 'Ticket unassigned.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket = $this->scoped($request, $ticket);

        $validated = $request->validate(['status' => ['required', Rule::in(['resolved', 'closed', 'open'])]]);

        match ($validated['status']) {
            'resolved' => $this->support->resolve($ticket, $request->user()),
            'closed' => $this->support->close($ticket, $request->user()),
            'open' => $this->support->reopen($ticket, $request->user()),
        };

        return back()->with('flash', "Ticket marked as {$validated['status']}.");
    }

    public function download(Request $request, SupportTicket $ticket, SupportTicketAttachment $attachment): SymfonyResponse
    {
        $ticket = $this->scoped($request, $ticket);

        abort_unless((int) $attachment->message->ticket_id === (int) $ticket->id, 404);

        // Internal attachments stay staff-only even for assigned staff
        // without broader rights — the desk itself is staff-only, and
        // portals never generate these URLs.
        if ($attachment->is_internal && ! $request->user()->can('support.view_all') && (int) $ticket->assigned_to !== (int) $request->user()->id) {
            abort(403);
        }

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    protected function scoped(Request $request, SupportTicket $ticket): SupportTicket
    {
        return SupportTicket::visibleTo($request->user())->findOrFail($ticket->id);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return collect(TicketStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()])->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function priorityOptions(): array
    {
        return collect(TicketPriority::cases())->map(fn ($p): array => ['value' => $p->value, 'label' => $p->label()])->all();
    }
}
