<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\TaxiBooking;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Vendor support portal. Vendors see only their own tickets; vendor
 * context (KYC/payout/tours) is offered via categories + own bookings.
 */
class SupportTicketController extends Controller
{
    public function __construct(protected SupportService $support) {}

    public function index(Request $request): Response
    {
        $tickets = SupportTicket::visibleTo($request->user())
            ->with(['category:id,name', 'assignee:id,name'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Vendor/Support/Index', ['tickets' => $tickets]);
    }

    public function create(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;

        $bookings = $profile
            ? Booking::assignedToVendor($profile->id)->latest()->limit(20)->get(['id', 'booking_reference_id'])
            : [];

        return Inertia::render('Vendor/Support/Create', [
            'categories' => SupportCategory::active()->get(['id', 'name']),
            'bookings' => $bookings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', 'exists:support_categories,id'],
            'priority' => ['nullable', Rule::in(array_column(TicketPriority::cases(), 'value'))],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'taxi_booking_id' => ['nullable', 'integer', 'exists:taxi_bookings,id'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $taxiBookingId = $this->ownedTaxiBookingId($request);

        $ticket = $this->support->open([
            ...$validated,
            'booking_id' => $this->ownedBookingId($request),
            'related_type' => $taxiBookingId ? TaxiBooking::class : null,
            'related_id' => $taxiBookingId,
        ], $request->user());

        return redirect()->route('vendor.support.show', $ticket)->with('flash', "Ticket {$ticket->reference} opened. Our team will reply here.");
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $this->scoped($request, $ticket);

        $ticket->load([
            'category:id,name',
            'assignee:id,name',
            'booking:id,booking_reference_id',
            'messages' => fn ($q) => $q->where('is_internal_note', false),
            'messages.author:id,name',
            'messages.attachments',
        ]);

        return Inertia::render('Vendor/Support/Show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->scoped($request, $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $this->support->reply($ticket, $request->user(), $validated['body'], $request->file('attachments', []));

        return back()->with('flash', 'Reply sent.');
    }

    public function download(Request $request, SupportTicket $ticket, SupportTicketAttachment $attachment): SymfonyResponse
    {
        $this->scoped($request, $ticket);

        abort_unless((int) $attachment->message->ticket_id === (int) $ticket->id, 404);
        abort_if((bool) $attachment->is_internal, 404);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    protected function scoped(Request $request, SupportTicket $ticket): SupportTicket
    {
        abort_unless((int) $ticket->requester_user_id === (int) $request->user()->id, 404);

        return $ticket;
    }

    protected function ownedBookingId(Request $request): ?int
    {
        $bookingId = $request->integer('booking_id');
        $profile = $request->user()->vendorProfile;

        if (! $bookingId || ! $profile) {
            return null;
        }

        return Booking::assignedToVendor($profile->id)->whereKey($bookingId)->exists() ? $bookingId : null;
    }

    protected function ownedTaxiBookingId(Request $request): ?int
    {
        $taxiBookingId = $request->integer('taxi_booking_id');
        $profile = $request->user()->vendorProfile;

        if (! $taxiBookingId || ! $profile) {
            return null;
        }

        return TaxiBooking::where('vendor_profile_id', $profile->id)->whereKey($taxiBookingId)->exists()
            ? $taxiBookingId
            : null;
    }
}
