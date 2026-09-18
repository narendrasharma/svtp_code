<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayoutAccount;
use App\Models\VendorProfile;
use App\Models\VendorVerification;
use App\Models\VendorWithdrawalRequest;
use App\Services\BookingService;
use App\Services\CancellationService;
use App\Services\VendorLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 7: vendor ledger + refund/reversal foundation + withdrawals.
 *
 * Covers earning credits, refund reversals, derived balances, the KYC
 * payout gate, hold/release/settlement accounting, admin review, security
 * scoping and key regressions. Amounts are exact decimal strings.
 */
class VendorLedgerWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Setting::setValue('platform_commission_percentage', '10');
        Setting::setValue('minimum_withdrawal_amount', '1000.00');
    }

    protected function bookings(): BookingService
    {
        return app(BookingService::class);
    }

    protected function ledger(): VendorLedgerService
    {
        return app(VendorLedgerService::class);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    protected function vendor(): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return $user->refresh();
    }

    protected function verifiedVendor(): User
    {
        $vendor = $this->vendor();
        $verification = VendorVerification::factory()->verified()->create(['user_id' => $vendor->id]);
        $vendor->vendorProfile->update([
            'vendor_verification_id' => $verification->id,
            'verification_status' => 'verified',
        ]);
        // Phase 8: withdrawals additionally require a verified payout
        // destination, so fully-eligible vendors carry one.
        VendorPayoutAccount::factory()->verified()->create(['vendor_profile_id' => $vendor->vendorProfile->id]);

        return $vendor->refresh();
    }

    protected function pendingKycVendor(): User
    {
        $vendor = $this->vendor();
        $verification = VendorVerification::factory()->pending()->create(['user_id' => $vendor->id]);
        $vendor->vendorProfile->update([
            'vendor_verification_id' => $verification->id,
            'verification_status' => 'pending',
        ]);

        return $vendor->refresh();
    }

    protected function vendorTour(VendorProfile $profile, array $overrides = []): TourPackage
    {
        return TourPackage::factory()->forVendor($profile)->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function adminTour(array $overrides = []): TourPackage
    {
        return TourPackage::factory()->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function book(TourPackage $tour, ?User $user = null): Booking
    {
        return $this->bookings()->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Ledger Guest', 'email' => 'ledger@example.com', 'phone' => '9876543210'],
            $user,
            BookingSource::Website
        );
    }

    protected function paidBooking(TourPackage $tour, ?User $user = null): Booking
    {
        $booking = $this->book($tour, $user);
        $this->bookings()->markPayment($booking, PaymentStatus::Paid);

        return $booking->refresh();
    }

    protected function balances(VendorProfile $profile): array
    {
        return $this->ledger()->balances($profile->id);
    }

    // ---- Earning credit --------------------------------------------------

    public function test_unpaid_booking_creates_no_earning_credit(): void
    {
        $vendor = $this->vendor();
        $this->book($this->vendorTour($vendor->vendorProfile));

        $this->assertSame(0, VendorLedgerEntry::count());
        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('0.00', $balances['recorded_earnings']);
        $this->assertSame('0.00', $balances['available_balance']);
    }

    public function test_paid_vendor_booking_creates_earning_credit_exactly_once(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $entries = VendorLedgerEntry::where('vendor_profile_id', $vendor->vendorProfile->id)->get();
        $this->assertCount(1, $entries);
        $this->assertSame(LedgerEntryType::BookingEarning, $entries->first()->type);
        $this->assertTrue($entries->first()->isCredit());
        $this->assertSame('9000.00', $entries->first()->amount);
        $this->assertSame(VendorLedgerService::earningReference($booking->id), $entries->first()->reference);
    }

    public function test_repeated_paid_update_creates_no_duplicate(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->bookings()->markPayment($booking, PaymentStatus::Paid);
        $this->ledger()->creditBookingEarning($booking->refresh());

        $this->assertSame(1, VendorLedgerEntry::where('reference', VendorLedgerService::earningReference($booking->id))->count());
    }

    public function test_credit_amount_uses_snapshotted_vendor_earning(): void
    {
        Setting::setValue('platform_commission_percentage', '25');

        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        // Gross 10000 @ 25% → earning 7500.
        $this->assertSame('7500.00', $booking->vendor_earning_amount);
        $this->assertSame('7500.00', VendorLedgerEntry::first()->amount);
    }

    public function test_admin_owned_paid_booking_creates_no_vendor_credit(): void
    {
        $this->paidBooking($this->adminTour());

        $this->assertSame(0, VendorLedgerEntry::count());
    }

    public function test_partially_paid_booking_creates_no_credit(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));
        $this->bookings()->markPayment($booking, PaymentStatus::PartiallyPaid);

        $this->assertSame(0, VendorLedgerEntry::count());
    }

    public function test_admin_marks_paid_through_http_credits_once(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($this->admin())
            ->patch(route('admin.bookings.status', $booking), ['payment_status' => 'paid'])
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->patch(route('admin.bookings.status', $booking), ['payment_status' => 'paid'])
            ->assertRedirect();

        $this->assertSame(1, VendorLedgerEntry::where('reference', VendorLedgerService::earningReference($booking->id))->count());
    }

    // ---- Refund / reversal -----------------------------------------------

    public function test_refunded_paid_booking_creates_reversal_and_keeps_credit(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->bookings()->markPayment($booking, PaymentStatus::Refunded);
        $booking = $booking->refresh();

        // Snapshots immutable.
        $this->assertSame('10000.00', $booking->gross_amount);
        $this->assertSame('10.00', $booking->platform_commission_percentage);
        $this->assertSame('1000.00', $booking->platform_commission_amount);
        $this->assertSame('9000.00', $booking->vendor_earning_amount);

        $entries = VendorLedgerEntry::orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame(LedgerEntryType::BookingEarning, $entries[0]->type);
        $this->assertSame(LedgerEntryType::RefundReversal, $entries[1]->type);
        $this->assertSame('9000.00', $entries[1]->amount);

        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('0.00', $balances['recorded_earnings']);
        $this->assertSame('0.00', $balances['available_balance']);
    }

    public function test_repeated_refund_creates_no_duplicate_reversal(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->bookings()->markPayment($booking, PaymentStatus::Refunded);
        $this->bookings()->markPayment($booking->refresh(), PaymentStatus::Refunded);
        $this->ledger()->reverseBookingEarning($booking->refresh());

        $this->assertSame(1, VendorLedgerEntry::where('reference', VendorLedgerService::reversalReference($booking->id))->count());
        $this->assertSame(2, VendorLedgerEntry::count());
    }

    public function test_unpaid_cancelled_then_refunded_creates_no_negative_balance(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $this->bookings()->changeStatus($booking, BookingStatus::Cancelled);
        $this->bookings()->markPayment($booking->refresh(), PaymentStatus::Refunded);

        $this->assertSame(0, VendorLedgerEntry::count());
        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('0.00', $balances['recorded_earnings']);
        $this->assertSame('0.00', $balances['available_balance']);
    }

    public function test_paid_booking_cancelled_but_not_refunded_keeps_earning(): void
    {
        $vendor = $this->vendor();
        $customer = $this->customer();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $cancellation = app(CancellationService::class)->request($booking, $customer, 'Change of plans');
        $this->assertTrue($cancellation->exists);
        app(CancellationService::class)->approve($cancellation, $this->admin());

        // Cancellation alone is not financial: earning stays, no reversal.
        $this->assertSame(1, VendorLedgerEntry::count());
        $this->assertSame('9000.00', $this->balances($vendor->vendorProfile)['recorded_earnings']);
    }

    // ---- Balances --------------------------------------------------------

    public function test_balance_formulas_are_exact(): void
    {
        $vendor = $this->verifiedVendor();
        $tour = $this->vendorTour($vendor->vendorProfile);

        $this->paidBooking($tour);
        $this->paidBooking($tour);

        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('18000.00', $balances['recorded_earnings']);
        $this->assertSame('0.00', $balances['held_amount']);
        $this->assertSame('18000.00', $balances['available_balance']);
        $this->assertSame('0.00', $balances['paid_out']);

        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '5000.00', $vendor);

        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('18000.00', $balances['recorded_earnings']);
        $this->assertSame('5000.00', $balances['held_amount']);
        $this->assertSame('13000.00', $balances['available_balance']);
        $this->assertSame('0.00', $balances['paid_out']);

        $this->ledger()->approveWithdrawal($request, $this->admin());
        $this->ledger()->markWithdrawalPaid($request, $this->admin(), 'UTR123');

        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('18000.00', $balances['recorded_earnings']);
        $this->assertSame('0.00', $balances['held_amount']);
        $this->assertSame('13000.00', $balances['available_balance']);
        $this->assertSame('5000.00', $balances['paid_out']);
    }

    public function test_decimal_precision_stays_exact(): void
    {
        $vendor = $this->vendor();
        // 2 adults × 999.99 = 1999.98 gross @ 10% → 200.00 commission, 1799.98 earning.
        $booking = $this->book($this->vendorTour($vendor->vendorProfile, ['price' => 999.99]));
        $this->bookings()->markPayment($booking, PaymentStatus::Paid);

        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('1799.98', $balances['recorded_earnings']);
        $this->assertSame('1799.98', $balances['available_balance']);
    }

    // ---- Withdrawals -----------------------------------------------------

    public function test_verified_vendor_can_request_within_available(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $response = $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), [
            'amount' => '2000.00',
        ]);
        $response->assertRedirect(route('vendor.finance.index'));

        $request = VendorWithdrawalRequest::first();
        $this->assertSame(WithdrawalStatus::Pending, $request->status);
        $this->assertSame('2000.00', $request->amount);
        $this->assertSame($vendor->vendorProfile->id, (int) $request->vendor_profile_id);

        // Hold created exactly once; available reduced.
        $this->assertSame(1, VendorLedgerEntry::where('type', LedgerEntryType::WithdrawalHold->value)->count());
        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('2000.00', $balances['held_amount']);
        $this->assertSame('7000.00', $balances['available_balance']);
    }

    public function test_pending_kyc_vendor_cannot_request_withdrawal(): void
    {
        $vendor = $this->pendingKycVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, VendorWithdrawalRequest::count());
        $this->assertSame('9000.00', $this->balances($vendor->vendorProfile)['available_balance']);
    }

    public function test_vendor_without_any_verification_cannot_request(): void
    {
        $vendor = $this->vendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, VendorWithdrawalRequest::count());
    }

    public function test_request_above_available_is_rejected(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '9000.01'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, VendorWithdrawalRequest::count());
    }

    public function test_request_below_minimum_is_rejected(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '999.99'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, VendorWithdrawalRequest::count());
    }

    public function test_second_request_cannot_reuse_held_funds(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '5000.00'])
            ->assertRedirect();
        // Available now 4000 — second request of 4500 must fail.
        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '4500.00'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, VendorWithdrawalRequest::count());
    }

    public function test_rejected_request_releases_hold(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);

        $this->actingAs($this->admin())
            ->patch(route('admin.withdrawals.reject', $request), ['rejection_reason' => 'Bank details pending'])
            ->assertRedirect();

        $request = $request->refresh();
        $this->assertSame(WithdrawalStatus::Rejected, $request->status);
        $this->assertSame('Bank details pending', $request->rejection_reason);

        $this->assertSame(1, VendorLedgerEntry::where('type', LedgerEntryType::WithdrawalRelease->value)->count());
        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('0.00', $balances['held_amount']);
        $this->assertSame('9000.00', $balances['available_balance']);
    }

    public function test_reject_requires_reason(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);

        $this->actingAs($this->admin())
            ->patch(route('admin.withdrawals.reject', $request), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(WithdrawalStatus::Pending, $request->refresh()->status);
    }

    public function test_approved_request_remains_held(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);

        $this->actingAs($this->admin())
            ->patch(route('admin.withdrawals.approve', $request), ['admin_note' => 'Looks good'])
            ->assertRedirect();

        $this->assertSame(WithdrawalStatus::Approved, $request->refresh()->status);
        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('2000.00', $balances['held_amount']);
        $this->assertSame('7000.00', $balances['available_balance']);
    }

    public function test_mark_paid_settles_without_double_debit(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);
        $this->ledger()->approveWithdrawal($request, $this->admin());

        $this->actingAs($this->admin())
            ->patch(route('admin.withdrawals.mark-paid', $request), ['payout_reference' => 'UTR-999', 'admin_note' => 'NEFT done'])
            ->assertRedirect();

        $request = $request->refresh();
        $this->assertSame(WithdrawalStatus::Paid, $request->status);
        $this->assertSame('UTR-999', $request->payout_reference);
        $this->assertNotNull($request->paid_at);

        // Hold + release + settlement: exactly 3 withdrawal-linked entries.
        $this->assertSame(3, VendorLedgerEntry::where('withdrawal_request_id', $request->id)->count());

        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('9000.00', $balances['recorded_earnings']);
        $this->assertSame('0.00', $balances['held_amount']);
        $this->assertSame('7000.00', $balances['available_balance']);
        $this->assertSame('2000.00', $balances['paid_out']);
    }

    public function test_repeated_mark_paid_is_idempotent(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);
        $this->ledger()->approveWithdrawal($request, $this->admin());

        $this->ledger()->markWithdrawalPaid($request, $this->admin(), 'UTR-1');
        $countAfterFirst = VendorLedgerEntry::count();
        $this->ledger()->markWithdrawalPaid($request->refresh(), $this->admin(), 'UTR-1');

        $this->assertSame($countAfterFirst, VendorLedgerEntry::count());
        $this->assertSame('2000.00', $this->balances($vendor->vendorProfile)['paid_out']);
    }

    public function test_vendor_cannot_approve_own_request(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);

        $this->actingAs($vendor)->patch(route('admin.withdrawals.approve', $request))->assertForbidden();
        $this->actingAs($vendor)->patch(route('admin.withdrawals.mark-paid', $request))->assertForbidden();

        $this->assertSame(WithdrawalStatus::Pending, $request->refresh()->status);
    }

    public function test_vendor_can_cancel_own_pending_request(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);

        $this->actingAs($vendor)->patch(route('vendor.withdrawals.cancel', $request))->assertRedirect();

        $this->assertSame(WithdrawalStatus::Cancelled, $request->refresh()->status);
        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('0.00', $balances['held_amount']);
        $this->assertSame('9000.00', $balances['available_balance']);
    }

    public function test_vendor_cannot_cancel_other_vendor_request(): void
    {
        $vendorA = $this->verifiedVendor();
        $vendorB = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendorA->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendorA->vendorProfile, '2000.00', $vendorA);

        $this->actingAs($vendorB)->patch(route('vendor.withdrawals.cancel', $request))->assertForbidden();
        $this->assertSame(WithdrawalStatus::Pending, $request->refresh()->status);
    }

    public function test_financial_fields_are_not_mass_assignable_on_withdrawal(): void
    {
        $vendor = $this->verifiedVendor();
        $other = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), [
            'amount' => '2000.00',
            'vendor_profile_id' => $other->vendorProfile->id,
            'status' => 'paid',
            'payout_reference' => 'FORGED',
        ])->assertRedirect();

        $request = VendorWithdrawalRequest::first();
        $this->assertSame($vendor->vendorProfile->id, (int) $request->vendor_profile_id);
        $this->assertSame(WithdrawalStatus::Pending, $request->status);
        $this->assertNull($request->payout_reference);
    }

    // ---- Manual adjustments ----------------------------------------------

    public function test_admin_manual_adjustment_credit_and_debit(): void
    {
        $vendor = $this->vendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->ledger()->adjust($vendor->vendorProfile, LedgerEntryType::AdjustmentCredit, '500.00', 'Goodwill credit', $this->admin());
        $this->assertSame('9500.00', $this->balances($vendor->vendorProfile)['recorded_earnings']);

        $this->ledger()->adjust($vendor->vendorProfile, LedgerEntryType::AdjustmentDebit, '200.00', 'Support correction', $this->admin());
        $balances = $this->balances($vendor->vendorProfile);
        $this->assertSame('9300.00', $balances['recorded_earnings']);
        $this->assertSame('9300.00', $balances['available_balance']);
    }

    public function test_manual_adjustment_requires_note_and_valid_type(): void
    {
        $vendor = $this->vendor();

        try {
            $this->ledger()->adjust($vendor->vendorProfile, LedgerEntryType::AdjustmentCredit, '100.00', '   ', $this->admin());
            $this->fail('Expected validation exception for empty note.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }

        try {
            $this->ledger()->adjust($vendor->vendorProfile, LedgerEntryType::BookingEarning, '100.00', 'Wrong type', $this->admin());
            $this->fail('Expected validation exception for invalid type.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('type', $e->errors());
        }
    }

    // ---- Security / scoping ----------------------------------------------

    public function test_vendor_sees_only_own_ledger_and_withdrawals(): void
    {
        $vendorA = $this->verifiedVendor();
        $vendorB = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendorA->vendorProfile));
        $this->paidBooking($this->vendorTour($vendorB->vendorProfile));
        $requestA = $this->ledger()->requestWithdrawal($vendorA->vendorProfile, '2000.00', $vendorA);

        $this->actingAs($vendorA)->get(route('vendor.finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balances.recorded_earnings', '9000.00')
                ->has('ledger.data', 2)
                ->has('withdrawals.data', 1)
                ->where('withdrawals.data.0.id', $requestA->id));

        $entryB = VendorLedgerEntry::where('vendor_profile_id', $vendorB->vendorProfile->id)->first();
        $this->assertFalse($vendorA->can('view', $entryB));
        $this->assertTrue($vendorA->can('view', $requestA));
        $this->assertFalse($vendorB->can('view', $requestA));
    }

    public function test_customer_and_guest_blocked_from_finance(): void
    {
        // Guest first: actingAs() persists, so unauthenticated calls must
        // come before any actingAs() in the same test.
        $this->get(route('vendor.finance.index'))->assertRedirect(route('login'));

        $this->actingAs($this->customer())->get(route('vendor.finance.index'))->assertForbidden();
        $this->actingAs($this->customer())->post(route('vendor.withdrawals.store'), ['amount' => '1000'])->assertForbidden();
    }

    public function test_admin_sees_all_withdrawals_with_filters(): void
    {
        $vendorA = $this->verifiedVendor();
        $vendorB = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendorA->vendorProfile));
        $this->paidBooking($this->vendorTour($vendorB->vendorProfile));
        $requestA = $this->ledger()->requestWithdrawal($vendorA->vendorProfile, '2000.00', $vendorA);
        $requestB = $this->ledger()->requestWithdrawal($vendorB->vendorProfile, '1500.00', $vendorB);
        $this->ledger()->approveWithdrawal($requestB, $this->admin());

        $this->actingAs($this->admin())->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('withdrawals.data', 2));

        $this->actingAs($this->admin())->get(route('admin.withdrawals.index', ['status' => 'approved']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('withdrawals.data', 1)
                ->where('withdrawals.data.0.id', $requestB->id));

        $this->actingAs($this->admin())->get(route('admin.withdrawals.index', ['vendor' => $vendorA->vendorProfile->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('withdrawals.data', 1)
                ->where('withdrawals.data.0.id', $requestA->id));
    }

    public function test_admin_withdrawal_detail_shows_balances_and_kyc(): void
    {
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);

        $this->actingAs($this->admin())->get(route('admin.withdrawals.show', $request))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('withdrawal.id', $request->id)
                ->where('kyc.verified', true)
                ->where('balances.available_balance', '7000.00')
                ->where('balances.held_amount', '2000.00'));
    }

    public function test_impersonated_vendor_can_view_but_cannot_request(): void
    {
        $admin = $this->admin();
        $vendor = $this->verifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendor))->assertRedirect();

        $this->get(route('vendor.finance.index'))->assertOk();
        $this->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])->assertForbidden();
        $this->assertSame(0, VendorWithdrawalRequest::count());
    }

    // ---- Booking integration ----------------------------------------------

    public function test_admin_booking_detail_shows_ledger_state(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($this->admin())->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('ledgerState', 'credited'));
    }

    public function test_vendor_booking_detail_shows_financial_state(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $response = $this->actingAs($vendor)->get(route('vendor.bookings.show', $booking));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']['booking']);
        $this->assertStringContainsString('ledger_state', $json);
        $this->assertStringContainsString('credited', $json);
    }

    public function test_ledger_state_transitions(): void
    {
        $vendor = $this->vendor();
        $unpaid = $this->book($this->vendorTour($vendor->vendorProfile));
        $adminOwned = $this->book($this->adminTour());

        $this->assertSame('pending_payment', $this->ledger()->ledgerStateForBooking($unpaid));
        $this->assertSame('not_eligible', $this->ledger()->ledgerStateForBooking($adminOwned));

        $paid = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $this->assertSame('credited', $this->ledger()->ledgerStateForBooking($paid));

        $this->bookings()->markPayment($paid, PaymentStatus::Refunded);
        $this->assertSame('reversed', $this->ledger()->ledgerStateForBooking($paid->refresh()));
    }

    // ---- Regressions -------------------------------------------------------

    public function test_guest_booking_flow_still_works_without_ledger_side_effects(): void
    {
        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);

        $this->post(route('booking.store'), [
            'package_id' => $tour->id,
            'travel_date' => now()->addWeek()->toDateString(),
            'total_adults' => 2,
            'total_children' => 0,
            'customer_name' => 'Guest Traveller',
            'customer_phone' => '9000000001',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();
        $this->assertSame($vendor->vendorProfile->id, (int) $booking->vendor_profile_id);
        // Unpaid guest booking: snapshot exists, no ledger credit yet.
        $this->assertSame('9000.00', $booking->vendor_earning_amount);
        $this->assertSame(0, VendorLedgerEntry::count());
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $vendor = $this->vendor();
        $paid = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $unpaid = $this->book($this->vendorTour($vendor->vendorProfile));
        $this->paidBooking($this->adminTour());

        // Simulate pre-ledger rows by removing the auto-credited entry.
        VendorLedgerEntry::where('reference', VendorLedgerService::earningReference($paid->id))->delete();
        $this->assertSame(0, VendorLedgerEntry::count());

        $this->artisan('vendor-ledger:backfill')->assertSuccessful();
        $this->assertSame(1, VendorLedgerEntry::count());
        $this->assertSame('9000.00', VendorLedgerEntry::first()->amount);

        // Re-run: no duplicates; unpaid/admin rows untouched.
        $this->artisan('vendor-ledger:backfill')->assertSuccessful();
        $this->assertSame(1, VendorLedgerEntry::count());
        $this->assertSame('pending_payment', $this->ledger()->ledgerStateForBooking($unpaid->refresh()));
    }
}
