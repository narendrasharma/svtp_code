<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceType;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Booking;
use App\Models\LeadTimelineEntry;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TourPackage;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\CrmNotification;
use App\Support\ModuleManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Commercial offers. A quotation is never an invoice: totals are
 * server-computed from line items, revisions share one reference, and
 * only the newest revision is ever current.
 */
class QuotationService
{
    use NotifiesAdmins;

    public function __construct(
        protected TourBookingPricingService $pricing,
        protected TourAddonService $addons,
        protected TourAvailabilityService $availability,
        protected BookingService $bookings,
        protected LeadService $leads,
    ) {}

    /**
     * Server-authoritative totals for a set of line inputs.
     *
     * @param  array<int, array{quantity?: int, unit_price?: float, tax_amount?: float, discount_amount?: float}>  $lines
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    public function totals(array $lines, float $headerDiscount = 0.0): array
    {
        $subtotal = 0.0;
        $discount = round(max(0.0, $headerDiscount), 2);
        $tax = 0.0;

        foreach ($lines as $line) {
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $unit = round(max(0.0, (float) ($line['unit_price'] ?? 0)), 2);
            $lineDiscount = round(min(max(0.0, (float) ($line['discount_amount'] ?? 0)), $qty * $unit), 2);
            $lineTax = round(max(0.0, (float) ($line['tax_amount'] ?? 0)), 2);

            $subtotal = round($subtotal + $qty * $unit, 2);
            $discount = round($discount + $lineDiscount, 2);
            $tax = round($tax + $lineTax, 2);
        }

        $discount = (float) min($discount, $subtotal + $tax);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => round(max(0.0, $subtotal - $discount + $tax), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data  items: array<int, array{item_type?, product_id?, description, quantity?, unit_price?, tax_amount?, discount_amount?, metadata?}>
     */
    public function create(array $data, ?User $actor = null): Quotation
    {
        $items = $this->normalizeItems($data['items'] ?? []);

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one line item.']);
        }

        return DB::transaction(function () use ($data, $items, $actor): Quotation {
            $totals = $this->totals($items, (float) ($data['discount_amount'] ?? 0));

            $quotation = Quotation::create([
                'reference' => app(NumberSeriesService::class)->next('quotation'),
                'revision_number' => 1,
                'root_quotation_id' => null,
                'lead_id' => $data['lead_id'] ?? null,
                'customer_user_id' => $data['customer_user_id'] ?? null,
                'service_type' => $data['service_type'] ?? ServiceType::Tour->value,
                'status' => QuotationStatus::Draft->value,
                'currency' => $data['currency'] ?? 'INR',
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'total_amount' => $totals['total'],
                'terms' => $data['terms'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'created_by' => $actor?->id,
                'public_token' => Str::random(32),
            ]);

            $this->storeItems($quotation, $items);

            $this->leadEvent($quotation, LeadTimelineEntry::QUOTATION_CREATED, $actor);

            return $quotation->refresh();
        });
    }

    /**
     * New revision: the old row is frozen as superseded, the new row
     * carries the same reference with revision_number + 1 and becomes
     * the only current revision.
     *
     * @param  array<string, mixed>  $data
     */
    public function revise(Quotation $quotation, array $data, ?User $actor = null): Quotation
    {
        $current = $this->current($quotation);
        $this->assertCurrent($current, $quotation);

        if (! $current->status()->isEditable()) {
            throw ValidationException::withMessages(['quotation' => "Revision {$current->revision_number} is {$current->status} and cannot be revised."]);
        }

        $items = $this->normalizeItems($data['items'] ?? $this->itemsInput($current));

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one line item.']);
        }

        return DB::transaction(function () use ($current, $data, $items, $actor): Quotation {
            $totals = $this->totals($items, (float) ($data['discount_amount'] ?? $current->discount_amount));

            $revision = Quotation::create([
                'reference' => $current->reference,
                'revision_number' => $current->revision_number + 1,
                'root_quotation_id' => $current->root_quotation_id ?? $current->id,
                'lead_id' => $data['lead_id'] ?? $current->lead_id,
                'customer_user_id' => $data['customer_user_id'] ?? $current->customer_user_id,
                'service_type' => $data['service_type'] ?? $current->service_type,
                'status' => QuotationStatus::Draft->value,
                'currency' => $data['currency'] ?? $current->currency,
                'valid_until' => $data['valid_until'] ?? $current->valid_until?->toDateString(),
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'total_amount' => $totals['total'],
                'terms' => $data['terms'] ?? $current->terms,
                'internal_note' => $data['internal_note'] ?? $current->internal_note,
                'customer_note' => $data['customer_note'] ?? $current->customer_note,
                'created_by' => $actor?->id ?? $current->created_by,
                'public_token' => Str::random(32),
            ]);

            $this->storeItems($revision, $items);

            $current->update(['status' => QuotationStatus::Superseded->value]);

            return $revision->refresh();
        });
    }

    public function send(Quotation $quotation, ?User $actor = null): Quotation
    {
        $current = $this->current($quotation);
        $this->assertCurrent($current, $quotation);

        if (! in_array($current->status, [QuotationStatus::Draft->value, QuotationStatus::Sent->value], true)) {
            throw ValidationException::withMessages(['status' => 'Only drafts can be sent.']);
        }

        $current->update(['status' => QuotationStatus::Sent->value]);
        $this->leadEvent($current, LeadTimelineEntry::QUOTATION_SENT, $actor);

        if ($current->customer) {
            $current->customer->notify(new CrmNotification('quotation_sent', [
                'reference' => $current->displayReference(),
                'quotation_id' => $current->id,
                'total' => (string) $current->total_amount,
                'public_url' => route('quotations.public', $current->public_token, absolute: false),
            ]));
        }

        return $current->refresh();
    }

    public function accept(Quotation $quotation, ?User $actor = null): Quotation
    {
        $current = $this->current($quotation);
        $this->assertCurrent($current, $quotation);

        if (! in_array($current->status, [QuotationStatus::Sent->value, QuotationStatus::Viewed->value], true)) {
            throw ValidationException::withMessages(['status' => 'Only sent quotations can be accepted.']);
        }

        if ($current->isExpired()) {
            throw ValidationException::withMessages(['status' => 'This quotation has expired. Revise it first.']);
        }

        $current->update(['status' => QuotationStatus::Accepted->value, 'accepted_at' => now()]);
        $this->leadEvent($current, LeadTimelineEntry::QUOTATION_ACCEPTED, $actor);

        // One alert to every admin (creator included) — a single
        // mechanism instead of a separate creator-only ping.
        $this->notifyAdmins(new AdminAlert('quotation_accepted', [
            'reference' => $current->displayReference(),
            'quotation_id' => $current->id,
            'total' => (string) $current->total_amount,
        ]));

        return $current->refresh();
    }

    public function reject(Quotation $quotation, ?User $actor = null): Quotation
    {
        $current = $this->current($quotation);
        $this->assertCurrent($current, $quotation);

        if (! in_array($current->status, [QuotationStatus::Draft->value, QuotationStatus::Sent->value, QuotationStatus::Viewed->value], true)) {
            throw ValidationException::withMessages(['status' => 'This quotation can no longer be rejected.']);
        }

        $current->update(['status' => QuotationStatus::Rejected->value, 'rejected_at' => now()]);
        $this->leadEvent($current, LeadTimelineEntry::QUOTATION_REJECTED, $actor);

        return $current->refresh();
    }

    public function markExpired(Quotation $quotation, ?User $actor = null): Quotation
    {
        $current = $this->current($quotation);
        $this->assertCurrent($current, $quotation);

        if (! in_array($current->status, [QuotationStatus::Sent->value, QuotationStatus::Viewed->value], true)) {
            throw ValidationException::withMessages(['status' => 'Only open quotations can expire.']);
        }

        $current->update(['status' => QuotationStatus::Expired->value]);

        return $current->refresh();
    }

    /**
     * Convert the accepted revision into a booking. Availability is
     * rechecked, totals are recomputed live, and any drift from the
     * accepted price is surfaced — never silently applied.
     *
     * @param  array{package_id?: ?int, travel_date?: ?string, adults?: int, children?: int, addons?: array, coupon_code?: ?string, price_basis?: string, price_reason?: ?string, customer_user_id?: ?int, customer?: array}  $data
     * @return array{booking: Booking, live_total: float, difference: float}
     */
    public function convertToBooking(Quotation $quotation, array $data, ?User $actor = null): array
    {
        $current = $this->current($quotation);
        $this->assertCurrent($current, $quotation);

        if ($current->status !== QuotationStatus::Accepted->value) {
            throw ValidationException::withMessages(['quotation' => 'Only accepted quotations can be converted.']);
        }

        if ($current->service_type !== ServiceType::Tour->value) {
            throw ValidationException::withMessages(['service_type' => 'Only tour quotations can be converted while taxi/hotel modules are not implemented.']);
        }

        if (app(ModuleManager::class)->isDisabled(ModuleManager::TOURS)) {
            throw ValidationException::withMessages(['service_type' => 'The tours module is currently disabled.']);
        }

        $tourContext = $this->tourContext($current);
        $packageId = $data['package_id'] ?? $tourContext['package_id'];
        $package = TourPackage::find($packageId);

        if (! $package) {
            throw ValidationException::withMessages(['package_id' => 'Choose the tour package to book.']);
        }

        if ($package->vendor_profile_id && $actor && ! $actor->isAdmin()) {
            // Vendor conversions are handled by the vendor flow.
            throw ValidationException::withMessages(['package_id' => 'Vendor products convert through the vendor desk.']);
        }

        $travel = [
            'adults' => (int) ($data['adults'] ?? $tourContext['adults'] ?? 1),
            'children' => (int) ($data['children'] ?? $tourContext['children'] ?? 0),
            'travel_date' => (string) ($data['travel_date'] ?? $tourContext['travel_date'] ?? ''),
        ];

        if ($travel['travel_date'] === '') {
            throw ValidationException::withMessages(['travel_date' => 'A travel date is required to convert.']);
        }

        // Live recomputation BEFORE committing: availability + pricing.
        $this->availability->validateBookingDate($package->refresh(), $travel['travel_date']);

        $addonSelections = $data['addons'] ?? [];
        $resolved = $this->addons->resolve($package, is_array($addonSelections) ? $addonSelections : [], $travel['adults'], $travel['children']);
        $tourBase = $this->pricing->quote($package, $travel['adults'], $travel['children']);
        $liveTotal = round($tourBase['subtotal'] + $resolved['total'], 2);
        $difference = round($liveTotal - (float) $current->total_amount, 2);

        $basis = $data['price_basis'] ?? 'current';

        if ($difference !== 0.0 && $basis === 'quoted' && empty($data['price_reason'])) {
            throw ValidationException::withMessages(['price_reason' => 'The live price moved since acceptance. Record why the quoted price is honored.']);
        }

        $customer = $current->customer;
        $customerData = $data['customer'] ?? [];

        if (isset($data['customer_user_id']) && (int) $data['customer_user_id'] > 0) {
            $customer = User::findOrFail((int) $data['customer_user_id']);
        }

        return DB::transaction(function () use ($current, $package, $travel, $customer, $customerData, $addonSelections, $basis, $difference, $liveTotal, $data, $actor): array {
            $booking = $this->bookings->createTourBooking(
                $package,
                $travel,
                [
                    'name' => $customerData['name'] ?? $customer?->name ?? $current->lead?->name,
                    'email' => $customerData['email'] ?? $customer?->email ?? $current->lead?->email,
                    'phone' => $customerData['phone'] ?? $customer?->phone ?? $current->lead?->phone,
                ],
                $customer,
                BookingSource::Quotation,
                $actor,
                null,
                null,
                ['addons' => $addonSelections],
                $basis === 'quoted' && $difference !== 0.0
                    ? [
                        'mode' => 'quoted',
                        'quoted_total' => (float) $current->total_amount,
                        'reason' => $data['price_reason'] ?? null,
                        'quotation_reference' => $current->displayReference(),
                    ]
                    : null,
            );

            $booking->forceFill([
                'quotation_id' => $current->id,
                'quotation_revision_number' => $current->revision_number,
                'quoted_total_amount' => $current->total_amount,
            ])->save();

            $current->update([
                'status' => QuotationStatus::Converted->value,
                'converted_at' => now(),
                'converted_booking_id' => $booking->id,
            ]);

            if ($current->lead_id) {
                $lead = $current->lead;
                $lead->update(['status' => LeadStatus::QuotationSent->value]);
                $this->leads->markWon($lead->refresh(), $booking->id, $actor);
            }

            $this->bookings->logNote(
                $booking,
                $actor,
                "Converted from {$current->displayReference()}. Live total ₹{$liveTotal}, accepted ₹{$current->total_amount}".($difference !== 0.0 ? " (difference ₹{$difference}, basis: {$basis})" : ' (no drift)').'.',
                true
            );

            if ($booking->user) {
                $booking->user->notify(new CrmNotification('offline_booking_created', [
                    'booking_id' => $booking->id,
                    'reference' => $booking->booking_reference_id,
                    'total' => (string) $booking->total_amount,
                ]));
            }

            return ['booking' => $booking->refresh(), 'live_total' => $liveTotal, 'difference' => $difference];
        });
    }

    /**
     * The newest revision of a reference family. Older revisions are
     * frozen history and must never be mutated through these actions.
     */
    public function current(Quotation $quotation): Quotation
    {
        $rootId = $quotation->root_quotation_id ?? $quotation->id;

        return Quotation::where('reference', $quotation->reference)
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('root_quotation_id', $rootId))
            ->orderByDesc('revision_number')
            ->firstOrFail();
    }

    /**
     * @return array<int, Quotation>
     */
    public function history(Quotation $quotation): array
    {
        $rootId = $quotation->root_quotation_id ?? $quotation->id;

        return Quotation::where('reference', $quotation->reference)
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('root_quotation_id', $rootId))
            ->orderBy('revision_number')
            ->with('items')
            ->get()
            ->all();
    }

    protected function assertCurrent(Quotation $current, Quotation $given): void
    {
        if ($current->id !== $given->id) {
            throw ValidationException::withMessages(['quotation' => "Only the current revision (Rev {$current->revision_number}) can be changed. Rev {$given->revision_number} is frozen history."]);
        }
    }

    /**
     * Tour context remembered on the first tour line (package, dates,
     * pax) so conversion can prefill the desk. Later phases may promote
     * this to first-class columns.
     *
     * @return array{package_id: ?int, travel_date: ?string, adults: int, children: int}
     */
    public function tourContext(Quotation $quotation): array
    {
        $line = $quotation->items->firstWhere('item_type', QuotationItem::TYPE_TOUR);

        $meta = $line?->metadata ?? [];

        return [
            'package_id' => $line?->product_id ?? ($meta['package_id'] ?? null),
            'travel_date' => $meta['travel_date'] ?? null,
            'adults' => (int) ($meta['adults'] ?? 1),
            'children' => (int) ($meta['children'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeItems(array $raw): array
    {
        $items = [];

        foreach (array_values($raw) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $description = trim((string) ($row['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $type = (string) ($row['item_type'] ?? QuotationItem::TYPE_MANUAL);

            if (! in_array($type, QuotationItem::types(), true)) {
                throw ValidationException::withMessages(["items.{$index}.item_type" => 'Unknown item type.']);
            }

            $items[] = [
                'item_type' => $type,
                'product_id' => isset($row['product_id']) && $row['product_id'] !== '' ? (int) $row['product_id'] : null,
                'description' => mb_substr($description, 0, 255),
                'quantity' => max(1, min(999, (int) ($row['quantity'] ?? 1))),
                'unit_price' => round(max(0.0, (float) ($row['unit_price'] ?? 0)), 2),
                'tax_amount' => round(max(0.0, (float) ($row['tax_amount'] ?? 0)), 2),
                'discount_amount' => round(max(0.0, (float) ($row['discount_amount'] ?? 0)), 2),
                'metadata' => isset($row['metadata']) && is_array($row['metadata']) ? $row['metadata'] : null,
                'sort_order' => $index,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function storeItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            $lineTotal = round($item['quantity'] * $item['unit_price'] - min($item['discount_amount'], $item['quantity'] * $item['unit_price']) + $item['tax_amount'], 2);

            $quotation->items()->create($item + ['total_amount' => max(0.0, $lineTotal)]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function itemsInput(Quotation $quotation): array
    {
        return $quotation->items->map(fn ($item): array => [
            'item_type' => $item->item_type,
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'tax_amount' => (float) $item->tax_amount,
            'discount_amount' => (float) $item->discount_amount,
            'metadata' => $item->metadata,
        ])->all();
    }

    protected function leadEvent(Quotation $quotation, string $event, ?User $actor): void
    {
        if (! $quotation->lead_id) {
            return;
        }

        $quotation->loadMissing('lead');

        if (! $quotation->lead) {
            return;
        }

        if ($event === LeadTimelineEntry::QUOTATION_CREATED && $quotation->lead->status === LeadStatus::New->value) {
            $quotation->lead->update(['status' => LeadStatus::QuotationSent->value]);
        }

        $this->leads->log($quotation->lead, $event, $actor, [
            'quotation_id' => $quotation->id,
            'reference' => $quotation->displayReference(),
            'total' => (string) $quotation->total_amount,
        ]);
    }
}
