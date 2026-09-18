<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Enums\RefundStatus;
use App\Enums\WithdrawalStatus;
use App\Events\WithdrawalDecided;
use App\Events\WithdrawalRequested;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayoutAccount;
use App\Models\VendorProfile;
use App\Models\VendorWithdrawalRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vendor financial ledger domain service (Phase 7).
 *
 * Single home for every vendor money movement. Booking snapshots stay
 * immutable — all later movement is append-only ledger rows and balances
 * are always DERIVED here, never stored.
 *
 * Accounting (positive amounts + explicit direction):
 * - recorded  = booking_earning + adjustment_credit
 *               − refund_reversal − adjustment_debit
 * - held      = withdrawal_hold − withdrawal_release
 * - paid_out  = payout_settlement
 * - available = recorded − held − paid_out
 *
 * Cancellation alone never touches the ledger: only the paid → earning and
 * refunded → reversal payment transitions move money. An unpaid booking that
 * is cancelled or refunded had no earning, so no reversal is written.
 *
 * Money math uses integer paise throughout so totals stay exact. Currency is
 * stored per entry (booking currency for earning-derived rows); the product
 * operates in a single currency today, so balances aggregate across rows and
 * the currency column is preserved for a future multi-currency split.
 */
class VendorLedgerService
{
    public const MIN_WITHDRAWAL_SETTING_KEY = 'minimum_withdrawal_amount';

    public const DEFAULT_MIN_WITHDRAWAL = '1000.00';

    // ---- Balances ------------------------------------------------------

    /**
     * @return array{recorded_earnings: string, held_amount: string, available_balance: string, paid_out: string, currency: string}
     */
    public function balances(int $vendorProfileId): array
    {
        $paise = [
            'recorded' => 0,
            'held' => 0,
            'paid' => 0,
        ];
        $currency = 'INR';

        VendorLedgerEntry::where('vendor_profile_id', $vendorProfileId)
            ->orderBy('id')
            ->chunkById(500, function ($entries) use (&$paise, &$currency): void {
                foreach ($entries as $entry) {
                    $currency = $entry->currency ?? $currency;
                    $amount = self::toPaise((string) $entry->getRawOriginal('amount'));
                    $type = $entry->type instanceof LedgerEntryType
                        ? $entry->type
                        : LedgerEntryType::from((string) $entry->getRawOriginal('type'));

                    match ($type) {
                        LedgerEntryType::BookingEarning,
                        LedgerEntryType::AdjustmentCredit => $paise['recorded'] += $amount,
                        LedgerEntryType::RefundReversal,
                        LedgerEntryType::AdjustmentDebit => $paise['recorded'] -= $amount,
                        LedgerEntryType::WithdrawalHold => $paise['held'] += $amount,
                        LedgerEntryType::WithdrawalRelease => $paise['held'] -= $amount,
                        LedgerEntryType::PayoutSettlement => $paise['paid'] += $amount,
                    };
                }
            });

        $recorded = $paise['recorded'];
        $held = $paise['held'];
        $paid = $paise['paid'];

        return [
            'recorded_earnings' => self::fromPaise($recorded),
            'held_amount' => self::fromPaise($held),
            'available_balance' => self::fromPaise($recorded - $held - $paid),
            'paid_out' => self::fromPaise($paid),
            'currency' => $currency,
        ];
    }

    public function availableBalance(int $vendorProfileId): string
    {
        return $this->balances($vendorProfileId)['available_balance'];
    }

    // ---- Booking earning / reversal ------------------------------------

    public static function earningReference(int $bookingId): string
    {
        return "booking-earning:{$bookingId}";
    }

    public static function reversalReference(int $bookingId): string
    {
        return "booking-reversal:{$bookingId}";
    }

    /**
     * Credit a vendor booking's snapshotted earning exactly once.
     *
     * Only paid, vendor-owned bookings with a positive earning qualify.
     * Returns null when the booking is not eligible (admin-owned, unpaid,
     * zero earning) and the existing entry when already credited.
     */
    public function creditBookingEarning(Booking $booking, ?User $actor = null): ?VendorLedgerEntry
    {
        if ($booking->vendor_profile_id === null) {
            return null;
        }

        if ($booking->payment_status !== PaymentStatus::Paid) {
            return null;
        }

        if (self::toPaise((string) $booking->vendor_earning_amount) <= 0) {
            return null;
        }

        return DB::transaction(fn (): VendorLedgerEntry => $this->createEntryOnce([
            'vendor_profile_id' => (int) $booking->vendor_profile_id,
            'booking_id' => $booking->id,
            'withdrawal_request_id' => null,
            'type' => LedgerEntryType::BookingEarning->value,
            'direction' => LedgerDirection::Credit->value,
            'amount' => self::toDecimal((string) $booking->vendor_earning_amount),
            'currency' => $booking->currency ?? 'INR',
            'reference' => self::earningReference($booking->id),
            'description' => "Booking earning {$booking->booking_reference_id}",
            'metadata' => [
                'booking_reference_id' => $booking->booking_reference_id,
                'gross_amount' => (string) $booking->gross_amount,
                'platform_commission_percentage' => $booking->platform_commission_percentage,
            ],
            'created_by' => $actor?->id,
        ]));
    }

    /**
     * Append a full reversal debit for a previously credited earning.
     *
     * The original credit row is preserved. Does nothing when no earning was
     * ever credited (e.g. unpaid booking cancelled/refunded) or when the
     * reversal already exists — repeated refunds never double-reverse.
     *
     * Phase 8: once processed partial refunds exist for a booking, the
     * partial flow (BookingRefundService) owns reversal accounting and this
     * legacy path stands down to avoid double-reversing.
     */
    public function reverseBookingEarning(Booking $booking, ?User $actor = null, ?string $reason = null): ?VendorLedgerEntry
    {
        $earning = VendorLedgerEntry::where('reference', self::earningReference($booking->id))->first();

        if (! $earning || $booking->vendor_profile_id === null) {
            return null;
        }

        if (BookingRefund::where('booking_id', $booking->id)->where('status', RefundStatus::Processed->value)->exists()) {
            return VendorLedgerEntry::where('reference', self::reversalReference($booking->id))->first();
        }

        return DB::transaction(fn (): VendorLedgerEntry => $this->createEntryOnce([
            'vendor_profile_id' => (int) $booking->vendor_profile_id,
            'booking_id' => $booking->id,
            'withdrawal_request_id' => null,
            'type' => LedgerEntryType::RefundReversal->value,
            'direction' => LedgerDirection::Debit->value,
            'amount' => (string) $earning->amount,
            'currency' => $earning->currency,
            'reference' => self::reversalReference($booking->id),
            'description' => "Refund reversal {$booking->booking_reference_id}".($reason ? " — {$reason}" : ''),
            'metadata' => [
                'booking_reference_id' => $booking->booking_reference_id,
                'reverses_reference' => $earning->reference,
            ],
            'created_by' => $actor?->id,
        ], true));
    }

    /**
     * Ledger state of a booking for admin/vendor detail display.
     */
    public function ledgerStateForBooking(Booking $booking): string
    {
        if ($booking->vendor_profile_id === null) {
            return 'not_eligible';
        }

        if (VendorLedgerEntry::where('reference', self::reversalReference($booking->id))->exists()) {
            return 'reversed';
        }

        if (! VendorLedgerEntry::where('reference', self::earningReference($booking->id))->exists()) {
            return 'pending_payment';
        }

        // Phase 8: partial refunds reverse proportionally.
        $reversedPaise = self::toPaise($this->partialReversedTotal($booking->id));
        $earningPaise = self::toPaise((string) $booking->vendor_earning_amount);

        if ($reversedPaise > 0 && $reversedPaise < $earningPaise) {
            return 'partially_reversed';
        }

        if ($reversedPaise >= $earningPaise && $earningPaise > 0) {
            return 'reversed';
        }

        return 'credited';
    }

    /**
     * Sum of refund-record-linked partial reversals for a booking.
     */
    public function partialReversedTotal(int $bookingId): string
    {
        $total = 0;
        VendorLedgerEntry::where('booking_id', $bookingId)
            ->where('type', LedgerEntryType::RefundReversal->value)
            ->whereNotNull('booking_refund_id')
            ->orderBy('id')
            ->chunkById(200, function ($entries) use (&$total): void {
                foreach ($entries as $entry) {
                    $total += self::toPaise((string) $entry->getRawOriginal('amount'));
                }
            });

        return self::fromPaise($total);
    }

    // ---- Withdrawals ---------------------------------------------------

    public function minimumWithdrawalAmount(): string
    {
        $raw = Setting::getValue(self::MIN_WITHDRAWAL_SETTING_KEY, self::DEFAULT_MIN_WITHDRAWAL);

        if (! is_numeric($raw) || (float) $raw < 0) {
            return self::DEFAULT_MIN_WITHDRAWAL;
        }

        return number_format(round((float) $raw, 2), 2, '.', '');
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function withdrawalEligibility(VendorProfile $profile): array
    {
        if (! $profile->isKycVerified()) {
            return ['allowed' => false, 'reason' => 'Complete KYC verification to request payouts.'];
        }

        // Phase 8: a verified payout destination is required alongside KYC.
        // Identity verification and destination verification stay separate.
        $account = VendorPayoutAccount::where('vendor_profile_id', $profile->id)->first();

        if (! $account) {
            return ['allowed' => false, 'reason' => 'Add payout details to request withdrawals.'];
        }

        if ($account->status !== PayoutAccountStatus::Verified) {
            if ($account->status === PayoutAccountStatus::Rejected) {
                $reason = 'Payout details were rejected'.($account->rejection_reason ? ": {$account->rejection_reason}" : '').'. Update them to request withdrawals.';

                return ['allowed' => false, 'reason' => $reason];
            }

            return ['allowed' => false, 'reason' => 'Payout details are pending verification.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Vendor requests a payout: validates KYC/minimum/available server-side,
     * then creates the request plus a hold debit in one transaction so held
     * funds can never be requested twice.
     */
    public function requestWithdrawal(VendorProfile $profile, float|string $amount, ?User $actor = null, ?string $vendorNote = null): VendorWithdrawalRequest
    {
        $eligibility = $this->withdrawalEligibility($profile);

        if (! $eligibility['allowed']) {
            throw ValidationException::withMessages(['amount' => $eligibility['reason']]);
        }

        $amountDecimal = self::toDecimal($amount);
        $amountPaise = self::toPaise($amountDecimal);
        $minimumPaise = self::toPaise($this->minimumWithdrawalAmount());
        $availablePaise = self::toPaise($this->availableBalance($profile->id));

        if ($amountPaise <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        if ($amountPaise < $minimumPaise) {
            throw ValidationException::withMessages(['amount' => 'Minimum withdrawal is ₹'.self::fromPaise($minimumPaise).'.']);
        }

        if ($amountPaise > $availablePaise) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds your available balance of ₹'.self::fromPaise($availablePaise).'.']);
        }

        $request = DB::transaction(function () use ($profile, $amountDecimal, $actor, $vendorNote): VendorWithdrawalRequest {
            // Phase 8: snapshot the verified payout destination (masked
            // only — never secrets) so later account changes keep history.
            $account = VendorPayoutAccount::where('vendor_profile_id', $profile->id)
                ->where('status', PayoutAccountStatus::Verified->value)
                ->first();

            $request = VendorWithdrawalRequest::create([
                'vendor_profile_id' => $profile->id,
                'amount' => $amountDecimal,
                'currency' => 'INR',
                'status' => WithdrawalStatus::Pending->value,
                'requested_at' => now(),
                'vendor_note' => $vendorNote,
                'payout_account_id' => $account?->id,
                'payout_method' => $account?->method instanceof \BackedEnum ? $account->method->value : $account?->method,
                'payout_destination_masked' => $account?->maskedDestination(),
            ]);

            $this->createEntryOnce([
                'vendor_profile_id' => $profile->id,
                'booking_id' => null,
                'withdrawal_request_id' => $request->id,
                'type' => LedgerEntryType::WithdrawalHold->value,
                'direction' => LedgerDirection::Debit->value,
                'amount' => $amountDecimal,
                'currency' => $request->currency,
                'reference' => "withdrawal-hold:{$request->id}",
                'description' => "Withdrawal hold #{$request->id}",
                'metadata' => null,
                'created_by' => $actor?->id,
            ]);

            return $request;
        });

        WithdrawalRequested::dispatch($request);

        return $request;
    }

    /**
     * Vendor cancels their own pending request (admin may cancel
     * pending/approved via the same path). Releases the hold.
     */
    public function cancelWithdrawal(VendorWithdrawalRequest $request, ?User $actor = null): VendorWithdrawalRequest
    {
        return $this->transitionWithdrawal($request, WithdrawalStatus::Cancelled, $actor, releaseHold: true);
    }

    public function approveWithdrawal(VendorWithdrawalRequest $request, User $admin, ?string $adminNote = null): VendorWithdrawalRequest
    {
        if (! $request->status->canTransitionTo(WithdrawalStatus::Approved)) {
            throw ValidationException::withMessages(['status' => "Cannot approve a {$request->status->value} request."]);
        }

        $request = DB::transaction(function () use ($request, $admin, $adminNote): VendorWithdrawalRequest {
            $request->update([
                'status' => WithdrawalStatus::Approved->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $adminNote ?? $request->admin_note,
            ]);

            return $request;
        });

        WithdrawalDecided::dispatch($request, WithdrawalStatus::Approved);

        return $request;
    }

    public function rejectWithdrawal(VendorWithdrawalRequest $request, User $admin, string $reason, ?string $adminNote = null): VendorWithdrawalRequest
    {
        if (! $request->status->canTransitionTo(WithdrawalStatus::Rejected)) {
            throw ValidationException::withMessages(['status' => "Cannot reject a {$request->status->value} request."]);
        }

        return $this->transitionWithdrawal($request, WithdrawalStatus::Rejected, $admin, releaseHold: true, reason: $reason, adminNote: $adminNote);
    }

    /**
     * Record manual off-platform settlement. Converts the hold into a final
     * payout with a release + settlement pair (net available unchanged, paid
     * out increases). Idempotent: already-paid requests return as-is.
     */
    public function markWithdrawalPaid(VendorWithdrawalRequest $request, User $admin, ?string $payoutReference = null, ?string $adminNote = null): VendorWithdrawalRequest
    {
        if ($request->status === WithdrawalStatus::Paid) {
            return $request;
        }

        if (! $request->status->canTransitionTo(WithdrawalStatus::Paid)) {
            throw ValidationException::withMessages(['status' => "Cannot mark a {$request->status->value} request as paid."]);
        }

        $request = DB::transaction(function () use ($request, $admin, $payoutReference, $adminNote): VendorWithdrawalRequest {
            $request->update([
                'status' => WithdrawalStatus::Paid->value,
                'reviewed_by' => $request->reviewed_by ?? $admin->id,
                'reviewed_at' => $request->reviewed_at ?? now(),
                'paid_at' => now(),
                'payout_reference' => $payoutReference ?? $request->payout_reference,
                'admin_note' => $adminNote ?? $request->admin_note,
            ]);

            $this->releaseHold($request, $admin?->id);

            $this->createEntryOnce([
                'vendor_profile_id' => $request->vendor_profile_id,
                'booking_id' => null,
                'withdrawal_request_id' => $request->id,
                'type' => LedgerEntryType::PayoutSettlement->value,
                'direction' => LedgerDirection::Debit->value,
                'amount' => (string) $request->amount,
                'currency' => $request->currency,
                'reference' => "withdrawal-settle:{$request->id}",
                'description' => "Payout settlement #{$request->id}".($payoutReference ? " ({$payoutReference})" : ''),
                'metadata' => $payoutReference ? ['payout_reference' => $payoutReference] : null,
                'created_by' => $admin?->id,
            ]);

            return $request;
        });

        WithdrawalDecided::dispatch($request, WithdrawalStatus::Paid);

        return $request;
    }

    /**
     * Admin manual adjustment (support/accounting). Append-only with a
     * required note and audit actor. Credits/debits feed recorded earnings.
     *
     * Phase 8 rule: a debit may drive available balance negative when a
     * genuine correction requires it — withdrawals stay blocked while
     * available <= 0, and callers should surface the projected balance.
     */
    public function adjust(VendorProfile $profile, LedgerEntryType $type, float|string $amount, string $note, User $admin, ?string $externalReference = null): VendorLedgerEntry
    {
        if (! in_array($type, [LedgerEntryType::AdjustmentCredit, LedgerEntryType::AdjustmentDebit], true)) {
            throw ValidationException::withMessages(['type' => 'Only credit/debit adjustments are allowed.']);
        }

        $amountDecimal = self::toDecimal($amount);

        if (self::toPaise($amountDecimal) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Adjustment amount must be greater than zero.']);
        }

        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'A reason is required for manual adjustments.']);
        }

        return DB::transaction(fn (): VendorLedgerEntry => $this->createEntryOnce([
            'vendor_profile_id' => $profile->id,
            'booking_id' => null,
            'withdrawal_request_id' => null,
            'type' => $type->value,
            'direction' => $type->direction()->value,
            'amount' => $amountDecimal,
            'currency' => 'INR',
            'reference' => 'adjustment:'.Str::uuid()->toString(),
            'description' => $note,
            'metadata' => $externalReference ? ['external_reference' => $externalReference] : null,
            'created_by' => $admin->id,
        ]));
    }

    // ---- Internals -----------------------------------------------------

    protected function transitionWithdrawal(
        VendorWithdrawalRequest $request,
        WithdrawalStatus $to,
        ?User $actor,
        bool $releaseHold,
        ?string $reason = null,
        ?string $adminNote = null,
    ): VendorWithdrawalRequest {
        if (! $request->status->canTransitionTo($to)) {
            throw ValidationException::withMessages(['status' => "Cannot move request from {$request->status->value} to {$to->value}."]);
        }

        $request = DB::transaction(function () use ($request, $to, $actor, $releaseHold, $reason, $adminNote): VendorWithdrawalRequest {
            $request->update(array_filter([
                'status' => $to->value,
                'reviewed_by' => $actor?->id ?? $request->reviewed_by,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'admin_note' => $adminNote ?? $request->admin_note,
            ], fn ($value) => $value !== null));

            if ($releaseHold) {
                $this->releaseHold($request, $actor?->id);
            }

            return $request;
        });

        WithdrawalDecided::dispatch($request, $to);

        return $request;
    }

    protected function releaseHold(VendorWithdrawalRequest $request, ?int $actorId): VendorLedgerEntry
    {
        return $this->createEntryOnce([
            'vendor_profile_id' => $request->vendor_profile_id,
            'booking_id' => null,
            'withdrawal_request_id' => $request->id,
            'type' => LedgerEntryType::WithdrawalRelease->value,
            'direction' => LedgerDirection::Credit->value,
            'amount' => (string) $request->amount,
            'currency' => $request->currency,
            'reference' => "withdrawal-release:{$request->id}",
            'description' => "Withdrawal release #{$request->id}",
            'metadata' => null,
            'created_by' => $actorId,
        ], true);
    }

    /**
     * Insert guarded by the unique reference. On a race duplicate, return the
     * existing row instead of failing. When $expectExisting is false (fresh
     * earning/hold paths) a pre-existing reference also resolves to the
     * stored row — callers never create money twice.
     */
    protected function createEntryOnce(array $attributes, bool $expectExisting = false): VendorLedgerEntry
    {
        $existing = VendorLedgerEntry::where('reference', $attributes['reference'])->first();

        if ($existing) {
            return $existing;
        }

        try {
            return VendorLedgerEntry::create($attributes);
        } catch (QueryException $e) {
            $existing = VendorLedgerEntry::where('reference', $attributes['reference'])->first();

            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }

    public static function toPaise(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromPaise(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $abs = abs($paise);

        return $sign.(string) intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function toDecimal(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
