<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuotationStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Commercial offers (Phase 11.5B). All money math is server-side; the
 * builder preview is UX only. Mutations always target the current
 * revision — older revisions are frozen history (assertCurrent 422s).
 */
class QuotationController extends Controller
{
    public function __construct(protected QuotationService $quotations) {}

    public function index(Request $request): Response
    {
        $query = Quotation::query()
            ->with(['lead:id,reference,name', 'customer:id,name', 'creator:id,name'])
            ->withCount('items');

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($q) => $q
                ->where('reference', 'like', $term)
                ->orWhereHas('lead', fn ($l) => $l->where('name', 'like', $term))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        // One row per reference family: the newest revision only.
        // (Older revisions are frozen history, visible on the show page.)
        $query->whereNotExists(function ($q): void {
            $q->selectRaw('1')->from('quotations as newer')
                ->whereColumn('newer.reference', 'quotations.reference')
                ->whereColumn('newer.revision_number', '>', 'quotations.revision_number');
        });

        return Inertia::render('Admin/Quotations/Index', [
            'quotations' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'statuses' => collect(QuotationStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Quotations/Builder', [
            'quotation' => null,
            'history' => [],
            'lead' => $request->filled('lead_id') ? Lead::find($request->integer('lead_id'), ['id', 'reference', 'name', 'phone', 'email']) : null,
            'itemTypes' => QuotationItem::types(),
            'serviceTypes' => collect(ServiceType::cases())->map(fn ($t): array => ['value' => $t->value, 'label' => $t->label()]),
            'tours' => TourPackage::active()->orderBy('title')->limit(200)->get(['id', 'title', 'price', 'discounted_price']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $quotation = $this->quotations->create($this->validated($request), $request->user());

        return redirect()->route('admin.quotations.show', $quotation)->with('flash', "Quotation {$quotation->reference} drafted.");
    }

    public function show(Request $request, Quotation $quotation): Response
    {
        $quotation->load(['items', 'lead:id,reference,name', 'customer:id,name,email,phone', 'creator:id,name', 'convertedBooking:id,booking_reference_id']);

        return Inertia::render('Admin/Quotations/Show', [
            'quotation' => $quotation,
            'history' => $this->quotations->history($quotation),
            'tourContext' => $this->quotations->tourContext($quotation),
            'publicUrl' => route('quotations.public', $quotation->public_token),
            'permissions' => [
                'update' => $request->user()->can('quotations.update'),
                'send' => $request->user()->can('quotations.send'),
                'accept' => $request->user()->can('quotations.accept'),
                'convert' => $request->user()->can('quotations.convert'),
            ],
        ]);
    }

    public function edit(Quotation $quotation): Response
    {
        $quotation->load('items');

        return Inertia::render('Admin/Quotations/Builder', [
            'quotation' => $quotation,
            'history' => $this->quotations->history($quotation),
            'lead' => $quotation->lead()->first(['id', 'reference', 'name', 'phone', 'email']),
            'itemTypes' => QuotationItem::types(),
            'serviceTypes' => collect(ServiceType::cases())->map(fn ($t): array => ['value' => $t->value, 'label' => $t->label()]),
            'tours' => TourPackage::active()->orderBy('title')->limit(200)->get(['id', 'title', 'price', 'discounted_price']),
        ]);
    }

    /**
     * Draft-field edits on the current revision (re-price, dates, notes).
     * Structural change history goes through revise() instead.
     */
    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $current = $this->quotations->current($quotation);

        if ($current->id !== $quotation->id) {
            return back()->withErrors(['quotation' => 'Only the current revision can be edited.']);
        }

        if (! $current->status()->isEditable()) {
            return back()->withErrors(['status' => "A {$current->status} quotation cannot be edited. Revise it instead."]);
        }

        $data = $this->validated($request);
        $items = $this->quotations->totals(
            collect($data['items'])->map(fn ($i): array => [
                'quantity' => $i['quantity'] ?? 1,
                'unit_price' => $i['unit_price'] ?? 0,
                'tax_amount' => $i['tax_amount'] ?? 0,
                'discount_amount' => $i['discount_amount'] ?? 0,
            ])->all(),
            (float) ($data['discount_amount'] ?? 0)
        );

        $quotation->update([
            'lead_id' => $data['lead_id'] ?? null,
            'customer_user_id' => $data['customer_user_id'] ?? null,
            'service_type' => $data['service_type'] ?? $quotation->service_type,
            'currency' => $data['currency'] ?? $quotation->currency,
            'valid_until' => $data['valid_until'] ?? null,
            'subtotal' => $items['subtotal'],
            'discount_amount' => $items['discount'],
            'tax_amount' => $items['tax'],
            'total_amount' => $items['total'],
            'terms' => $data['terms'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
            'customer_note' => $data['customer_note'] ?? null,
        ]);

        $quotation->items()->delete();

        foreach ($data['items'] as $index => $row) {
            $lineTotal = round($row['quantity'] * $row['unit_price'] - min($row['discount_amount'], $row['quantity'] * $row['unit_price']) + $row['tax_amount'], 2);
            $quotation->items()->create($row + ['total_amount' => max(0.0, $lineTotal), 'sort_order' => $index]);
        }

        return back()->with('flash', 'Quotation updated.');
    }

    public function revise(Request $request, Quotation $quotation): RedirectResponse
    {
        $revision = $this->quotations->revise($quotation, $this->validated($request), $request->user());

        return redirect()->route('admin.quotations.show', $revision)->with('flash', "Revision {$revision->revision_number} created; Rev ".($revision->revision_number - 1).' is now superseded.');
    }

    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->send($quotation, $request->user());

        return back()->with('flash', 'Quotation marked as sent.');
    }

    public function accept(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->accept($quotation, $request->user());

        return back()->with('flash', 'Quotation accepted.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->reject($quotation, $request->user());

        return back()->with('flash', 'Quotation rejected.');
    }

    public function expire(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->markExpired($quotation, $request->user());

        return back()->with('flash', 'Quotation marked as expired.');
    }

    public function convertForm(Request $request, Quotation $quotation): Response
    {
        $quotation->load(['items', 'lead:id,reference,name,phone,email', 'customer:id,name,email,phone']);

        return Inertia::render('Admin/Quotations/Convert', [
            'quotation' => $quotation,
            'tourContext' => $this->quotations->tourContext($quotation),
            'tours' => TourPackage::active()->orderBy('title')->limit(200)->get(['id', 'title', 'price', 'discounted_price']),
            'customer' => $quotation->customer,
        ]);
    }

    public function convert(Request $request, Quotation $quotation): RedirectResponse
    {
        $validated = $request->validate([
            'package_id' => ['nullable', 'integer', 'exists:tour_packages,id'],
            'travel_date' => ['nullable', 'date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:100'],
            'children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required_with:addons', 'integer', 'exists:tour_addons,id'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:30'],
            'price_basis' => ['nullable', Rule::in(['current', 'quoted'])],
            'price_reason' => ['nullable', 'string', 'max:500'],
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
        ]);

        $result = $this->quotations->convertToBooking($quotation, $validated, $request->user());

        $message = "Booking {$result['booking']->booking_reference_id} created from quotation.";

        if ($result['difference'] !== 0.0) {
            $message .= ' Price drift ₹'.number_format($result['difference'], 2).' handled on '.($validated['price_basis'] ?? 'current').' basis.';
        }

        return redirect()->route('admin.bookings.show', $result['booking'])->with('flash', $message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_type' => ['nullable', Rule::in(array_column(ServiceType::cases(), 'value'))],
            'currency' => ['nullable', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'customer_note' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_type' => ['nullable', Rule::in(QuotationItem::types())],
            'items.*.product_id' => ['nullable', 'integer', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items.*.metadata' => ['nullable', 'array'],
        ]);

        if (! empty($validated['customer_user_id'])) {
            $customer = User::find($validated['customer_user_id']);

            if (! $customer || ! $customer->isCustomer()) {
                abort(422, 'Quotations can only target customer accounts.');
            }
        }

        // Normalize item defaults server-side; totals() recomputes anyway.
        $validated['items'] = collect($validated['items'])->map(fn ($row): array => [
            'item_type' => $row['item_type'] ?? QuotationItem::TYPE_MANUAL,
            'product_id' => $row['product_id'] ?? null,
            'description' => $row['description'],
            'quantity' => $row['quantity'] ?? 1,
            'unit_price' => $row['unit_price'] ?? 0,
            'tax_amount' => $row['tax_amount'] ?? 0,
            'discount_amount' => $row['discount_amount'] ?? 0,
            'metadata' => $row['metadata'] ?? null,
        ])->all();

        // Tour lines snapshot the live package for the audit trail.
        foreach ($validated['items'] as $i => $row) {
            if ($row['item_type'] === QuotationItem::TYPE_TOUR && $row['product_id']) {
                $package = TourPackage::find($row['product_id']);

                if ($package) {
                    $validated['items'][$i]['metadata'] = array_merge($row['metadata'] ?? [], [
                        'package_title' => $package->title,
                        'package_slug' => $package->slug,
                    ]);
                }
            }
        }

        return $validated;
    }
}
