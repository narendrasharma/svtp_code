<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tour booking domain service.
 *
 * All booking creation, pricing and status changes flow through here so
 * future web, admin, vendor, API and mobile clients share one implementation.
 * Controllers only validate input and translate service exceptions.
 */
class BookingService
{
    public function __construct(
        protected TourBookingPricingService $pricing,
        protected MarketplaceCommissionService $commissions,
        protected VendorLedgerService $ledger,
        protected TourAddonService $addons = new TourAddonService,
        protected CouponService $coupons = new CouponService,
        protected TourAvailabilityService $availability = new TourAvailabilityService,
    ) {}

    /**
     * Create a tour booking with a server-computed price snapshot.
     *
     * Phase 10 pricing order: tour base + add-ons = subtotal → coupon
     * discount → total. The total feeds the commission snapshot as gross.
     * Coupon/add-on/availability inputs are revalidated here — the final
     * transaction is authoritative, never the estimate preview.
     *
     * @param  array{adults: int, children?: int, travel_date: string}  $travel
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, country?: ?string, pickup_address?: ?string, special_requests?: ?string}  $customer
     * @param  array{addons?: array<int, array{addon_id: int, quantity?: int}>, coupon_code?: ?string}  $extras
     * @param  array{mode: string, quoted_total?: float, reason?: ?string, quotation_reference?: ?string}|null  $priceBasis
     *                                                                                                                       Accepted-quotation pricing: mode=current (default) uses the live
     *                                                                                                                       server quote; mode=quoted honors the accepted quotation total and
     *                                                                                                                       folds the difference into discount_amount so the snapshot stays
     *                                                                                                                       coherent (subtotal − discount + tax = total). Never client input —
     *                                                                                                                       only QuotationService passes this after revalidation.
     */
    public function createTourBooking(
        TourPackage $package,
        array $travel,
        array $customer,
        ?User $user,
        BookingSource $source,
        ?User $actor = null,
        ?BookingStatus $status = null,
        ?PaymentStatus $payment = null,
        array $extras = [],
        ?array $priceBasis = null,
    ): Booking {
        if (! $package->is_active || $package->moderation_status?->value !== 'approved') {
            throw ValidationException::withMessages(['package_id' => 'This tour is no longer available for booking.']);
        }

        $adults = (int) $travel['adults'];
        $children = (int) ($travel['children'] ?? 0);
        $travelDate = (string) $travel['travel_date'];
        $addonSelections = $extras['addons'] ?? [];
        $couponCode = isset($extras['coupon_code']) && trim((string) $extras['coupon_code']) !== ''
            ? trim((string) $extras['coupon_code'])
            : null;
        $bookingEmail = $customer['email'] ?? $user?->email;

        // Events fire after commit so listeners never notify for a booking
        // that failed to persist.
        $booking = DB::transaction(function () use ($package, $travelDate, $adults, $children, $addonSelections, $couponCode, $customer, $user, $source, $actor, $status, $payment, $bookingEmail, $priceBasis): Booking {
            // Server-enforced availability (never trust the date picker).
            $this->availability->validateBookingDate($package->refresh(), $travelDate);

            // Server-authoritative add-ons (prices from live rows).
            $resolved = $this->addons->resolve($package, is_array($addonSelections) ? $addonSelections : [], $adults, $children);
            $tourBase = $this->pricing->quote($package, $adults, $children);
            $subtotal = round($tourBase['subtotal'] + $resolved['total'], 2);

            // Server-authoritative coupon (revalidated + usage-locked).
            $coupon = null;
            $discount = 0.0;

            if ($couponCode !== null) {
                $redeemed = $this->coupons->redeemForBooking($couponCode, $package, $subtotal, $user, $bookingEmail);
                $coupon = $redeemed['coupon'];
                $discount = $redeemed['discount_amount'];
            }

            $quote = $this->pricing->quoteDetailed($package, $adults, $children, $resolved['lines'], $discount);
            $addonSnapshots = $this->addons->snapshotLines($resolved['lines']);

            // Accepted-quotation pricing: honor the staff-confirmed quoted
            // total (difference folded into discount_amount; negative means
            // the live price moved below the quote).
            $priceNote = null;

            if (($priceBasis['mode'] ?? 'current') === 'quoted' && isset($priceBasis['quoted_total'])) {
                $quotedTotal = round((float) $priceBasis['quoted_total'], 2);

                if ($quotedTotal < 0) {
                    throw ValidationException::withMessages(['price_basis' => 'Quoted total cannot be negative.']);
                }

                $quote['discount_amount'] = round($quote['subtotal'] - $quotedTotal + $quote['tax_amount'], 2);
                $quote['total_amount'] = $quotedTotal;
                $priceNote = 'Quoted price '.($priceBasis['quotation_reference'] ?? '').' honored (live total differed): '.($priceBasis['reason'] ?? 'staff decision');
            }

            // Historical vendor + commission snapshot. Uses the server-side
            // quote total as gross — never anything from the client. Stored
            // via forceFill (not $fillable) so no mass-assignment path can
            // ever set these fields.
            $snapshot = $this->commissions->snapshotForTour($package, $quote['total_amount']);

            $booking = BookingReference::createBooking([
                'product_type' => 'tour',
                'source' => $source->value,
                'user_id' => $user?->id,
                'package_id' => $package->id,
                'customer_name' => $customer['name'] ?? $user?->name,
                'customer_email' => $customer['email'] ?? $user?->email,
                'customer_phone' => $customer['phone'] ?? $user?->phone,
                'country' => $customer['country'] ?? null,
                'pickup_address' => $customer['pickup_address'] ?? null,
                'special_requests' => $customer['special_requests'] ?? null,
                'travel_date' => $travelDate,
                'total_adults' => $quote['total_adults'],
                'total_children' => $quote['total_children'],
                'currency' => $quote['currency'],
                'base_price' => $quote['base_price'],
                'subtotal' => $quote['subtotal'],
                'discount_amount' => $quote['discount_amount'],
                'tax_amount' => $quote['tax_amount'],
                'total_amount' => $quote['total_amount'],
                'booking_status' => ($status ?? BookingStatus::Pending)->value,
                'payment_status' => ($payment ?? PaymentStatus::Unpaid)->value,
            ]);

            $booking->forceFill([
                'vendor_profile_id' => $snapshot['vendor_profile_id'],
                'gross_amount' => $snapshot['gross_amount'],
                'platform_commission_percentage' => $snapshot['platform_commission_percentage'],
                'platform_commission_amount' => $snapshot['platform_commission_amount'],
                'vendor_earning_amount' => $snapshot['vendor_earning_amount'],
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'coupon_discount_type' => $coupon?->discount_type,
                'coupon_discount_value' => $coupon !== null ? number_format((float) $coupon->discount_value, 2, '.', '') : null,
                'addons_total' => number_format($quote['addons_total'], 2, '.', ''),
            ])->save();

            foreach ($addonSnapshots as $snapshotLine) {
                $booking->bookingAddons()->create($snapshotLine);
            }

            if ($coupon !== null) {
                $this->coupons->recordRedemption($coupon, $booking->id, $user, $bookingEmail, $quote['discount_amount']);
            }

            $this->recordHistory($booking, null, $booking->booking_status->value, null, null, $actor?->id, $priceNote ?? 'Booking created');

            return $booking;
        });

        BookingCreated::dispatch($booking);

        return $booking;
    }

    /**
     * Move a booking through its lifecycle. Rejects terminal states and
     * invalid jumps instead of allowing arbitrary status strings.
     */
    public function changeStatus(Booking $booking, BookingStatus $to, ?User $actor = null, ?string $note = null): Booking
    {
        $from = $booking->booking_status;

        if ($from === $to) {
            return $booking;
        }

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'booking_status' => "Cannot move booking from {$from->value} to {$to->value}.",
            ]);
        }

        $booking = DB::transaction(function () use ($booking, $from, $to, $actor, $note): Booking {
            $booking->update(['booking_status' => $to->value]);
            $this->recordHistory($booking, $from->value, $to->value, null, null, $actor?->id, $note);

            return $booking;
        });

        BookingStatusChanged::dispatch($booking->refresh(), $from, $to, $actor);

        return $booking;
    }

    /**
     * Record a payment state change (gateways and webhooks reuse this later).
     *
     * Phase 7 ledger hooks: unpaid → paid credits the vendor earning exactly
     * once; any → refunded appends a reversal debit (never deletes the
     * credit). Booking snapshot columns stay immutable.
     */
    public function markPayment(Booking $booking, PaymentStatus $to, ?User $actor = null, ?string $note = null): Booking
    {
        $from = $booking->payment_status;

        if ($from === $to) {
            return $booking;
        }

        return DB::transaction(function () use ($booking, $from, $to, $actor, $note): Booking {
            $booking->update(['payment_status' => $to->value]);
            $this->recordHistory(
                $booking,
                $booking->booking_status->value,
                $booking->booking_status->value,
                $from->value,
                $to->value,
                $actor?->id,
                $note ?? "Payment {$from->value} → {$to->value}"
            );

            if ($to === PaymentStatus::Paid) {
                $this->ledger->creditBookingEarning($booking->refresh(), $actor);
            }

            if ($to === PaymentStatus::Refunded) {
                $this->ledger->reverseBookingEarning($booking->refresh(), $actor, $note);
            }

            return $booking;
        });
    }

    protected function recordHistory(
        Booking $booking,
        ?string $from,
        string $to,
        ?string $paymentFrom,
        ?string $paymentTo,
        ?int $actorId,
        ?string $note,
        bool $internal = false,
    ): void {
        $booking->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'payment_from' => $paymentFrom,
            'payment_to' => $paymentTo,
            'changed_by' => $actorId,
            'note' => $note,
            'is_internal' => $internal,
        ]);
    }

    /**
     * Append an operational note to the booking timeline without changing
     * any state. Internal notes stay inside the admin area; customer and
     * vendor shapes must filter them out.
     */
    public function logNote(Booking $booking, ?User $actor, string $note, bool $internal = false): void
    {
        $status = $booking->booking_status->value;
        $payment = $booking->payment_status->value;
        $this->recordHistory($booking, $status, $status, $payment, $payment, $actor?->id, $note, $internal);
    }
}
