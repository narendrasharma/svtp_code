<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayoutAccount;
use App\Models\VendorProfile;
use App\Models\VendorVerification;
use App\Models\VendorWithdrawalRequest;
use App\Services\BookingRefundService;
use App\Services\BookingService;
use App\Services\PayoutAccountService;
use App\Services\Payouts\ManualPayoutProcessor;
use App\Services\Payouts\PayoutProcessor;
use App\Services\VendorLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 8: payout accounts + partial refunds + admin adjustments + payout seam.
 */
class VendorPayoutRefundTest extends TestCase
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

    protected function refunds(): BookingRefundService
    {
        return app(BookingRefundService::class);
    }

    protected function payoutAccounts(): PayoutAccountService
    {
        return app(PayoutAccountService::class);
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

    protected function kycVerifiedVendor(): User
    {
        $vendor = $this->vendor();
        $verification = VendorVerification::factory()->verified()->create(['user_id' => $vendor->id]);
        $vendor->vendorProfile->update([
            'vendor_verification_id' => $verification->id,
            'verification_status' => 'verified',
        ]);

        return $vendor->refresh();
    }

    protected function fullVendor(): User
    {
        $vendor = $this->kycVerifiedVendor();
        VendorPayoutAccount::factory()->verified()->create(['vendor_profile_id' => $vendor->vendorProfile->id]);

        return $vendor->refresh();
    }

    protected function bankData(array $overrides = []): array
    {
        return array_merge([
            'method' => 'bank',
            'account_holder_name' => 'Test Vendor',
            'bank_name' => 'Test Bank',
            'account_number' => '411111111111',
            'ifsc' => 'HDFC0001234',
        ], $overrides);
    }

    protected function upiData(array $overrides = []): array
    {
        return array_merge([
            'method' => 'upi',
            'account_holder_name' => 'Test Vendor',
            'upi_id' => 'testvendor@upi',
        ], $overrides);
    }

    protected function vendorTour(VendorProfile $profile, array $overrides = []): TourPackage
    {
        return TourPackage::factory()->forVendor($profile)->create(array_merge(
            ['price' => 5000, 'discounted_price' => null, 'is_active' => true, 'moderation_status' => 'approved'],
            $overrides
        ));
    }

    protected function book(TourPackage $tour, ?User $user = null): Booking
    {
        return $this->bookings()->createTourBooking(
            $tour,
            ['adults' => 2, 'children' => 0, 'travel_date' => now()->addWeek()->toDateString()],
            ['name' => 'Refund Guest', 'email' => 'refund@example.com', 'phone' => '9876543210'],
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

    // ---- Payout accounts -------------------------------------------------

    public function test_vendor_can_create_bank_payout_account(): void
    {
        $vendor = $this->kycVerifiedVendor();

        $this->actingAs($vendor)->post(route('vendor.payout-account.store'), $this->bankData())
            ->assertRedirect(route('vendor.finance.index'));

        $account = VendorPayoutAccount::first();
        $this->assertSame($vendor->vendorProfile->id, (int) $account->vendor_profile_id);
        $this->assertSame(PayoutAccountStatus::Pending, $account->status);
        $this->assertSame('XXXXXXXX1111', $account->maskedDestination());
        $this->assertSame('1111', $account->account_number_last4);
    }

    public function test_vendor_can_create_upi_payout_account(): void
    {
        $vendor = $this->kycVerifiedVendor();

        $this->actingAs($vendor)->post(route('vendor.payout-account.store'), $this->upiData())
            ->assertRedirect(route('vendor.finance.index'));

        $account = VendorPayoutAccount::first();
        $this->assertSame('te***@upi', $account->maskedDestination());
        $this->assertSame('te***@upi', $account->upi_id_masked);
    }

    public function test_sensitive_values_encrypted_at_rest(): void
    {
        $vendor = $this->kycVerifiedVendor();
        $this->actingAs($vendor)->post(route('vendor.payout-account.store'), $this->bankData());

        $raw = VendorPayoutAccount::first()->getRawOriginal('account_number');
        $this->assertNotSame('411111111111', $raw);
        $this->assertSame('411111111111', Crypt::decryptString($raw));
    }

    public function test_frontend_receives_masked_value_only(): void
    {
        $vendor = $this->fullVendor();

        $response = $this->actingAs($vendor)->get(route('vendor.finance.index'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertSame('XXXXXXXX9012', $props['payoutAccount']['masked_destination']);
        $this->assertArrayNotHasKey('account_number', $props['payoutAccount']);
        $this->assertArrayNotHasKey('upi_id', $props['payoutAccount']);

        $json = json_encode($props);
        $this->assertStringNotContainsString('123456789012', $json);
    }

    public function test_vendor_cannot_mark_payout_verified(): void
    {
        $vendor = $this->kycVerifiedVendor();

        $this->actingAs($vendor)->post(route('vendor.payout-account.store'), $this->bankData() + [
            'status' => 'verified',
            'verified_by' => $vendor->id,
        ])->assertRedirect();

        $account = VendorPayoutAccount::first();
        $this->assertSame(PayoutAccountStatus::Pending, $account->status);
        $this->assertNull($account->verified_by);

        $this->actingAs($vendor)->patch(route('admin.payout-accounts.verify', $account))->assertForbidden();
        $this->assertSame(PayoutAccountStatus::Pending, $account->refresh()->status);
    }

    public function test_second_vendor_cannot_access_payout_account(): void
    {
        $vendorA = $this->fullVendor();
        $vendorB = $this->kycVerifiedVendor();
        $accountA = $vendorA->vendorProfile->payoutAccount;

        $this->assertFalse($vendorB->can('view', $accountA));
        $this->assertTrue($vendorA->can('view', $accountA));

        // Finance page only ever carries the viewer's own account.
        $this->actingAs($vendorB)->get(route('vendor.finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('payoutAccount', null));
    }

    public function test_admin_can_verify_payout_account(): void
    {
        $vendor = $this->kycVerifiedVendor();
        $account = $this->payoutAccounts()->save($vendor->vendorProfile, $this->bankData());
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.payout-accounts.verify', $account))->assertRedirect();

        $account = $account->refresh();
        $this->assertSame(PayoutAccountStatus::Verified, $account->status);
        $this->assertSame($admin->id, (int) $account->verified_by);
        $this->assertNotNull($account->verified_at);
    }

    public function test_admin_can_reject_payout_account_with_reason(): void
    {
        $vendor = $this->kycVerifiedVendor();
        $account = $this->payoutAccounts()->save($vendor->vendorProfile, $this->bankData());

        $this->actingAs($this->admin())->patch(route('admin.payout-accounts.reject', $account), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($this->admin())
            ->patch(route('admin.payout-accounts.reject', $account), ['rejection_reason' => 'Name mismatch'])
            ->assertRedirect();

        $account = $account->refresh();
        $this->assertSame(PayoutAccountStatus::Rejected, $account->status);
        $this->assertSame('Name mismatch', $account->rejection_reason);
    }

    public function test_rejected_vendor_can_replace_details(): void
    {
        $vendor = $this->kycVerifiedVendor();
        $account = $this->payoutAccounts()->save($vendor->vendorProfile, $this->bankData());
        $this->payoutAccounts()->reject($account, $this->admin(), 'Name mismatch');

        $this->actingAs($vendor)->post(route('vendor.payout-account.store'), $this->upiData())
            ->assertRedirect();

        // One row per vendor: replaced, reset to pending, reason cleared.
        $this->assertSame(1, VendorPayoutAccount::count());
        $account = $account->refresh();
        $this->assertSame(PayoutAccountStatus::Pending, $account->status);
        $this->assertNull($account->rejection_reason);
        $this->assertSame('te***@upi', $account->maskedDestination());
    }

    public function test_impersonated_vendor_cannot_edit_payout_account(): void
    {
        $admin = $this->admin();
        $vendor = $this->fullVendor();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendor))->assertRedirect();

        $this->get(route('vendor.finance.index'))->assertOk();
        $this->post(route('vendor.payout-account.store'), $this->bankData())->assertForbidden();
        $this->assertSame(1, VendorPayoutAccount::count());
    }

    public function test_admin_payout_index_filters_and_masks(): void
    {
        $verified = $this->fullVendor();
        $pending = $this->kycVerifiedVendor();
        $this->payoutAccounts()->save($pending->vendorProfile, $this->bankData());

        $this->actingAs($this->admin())->get(route('admin.payout-accounts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('accounts.data', 2));

        $response = $this->actingAs($this->admin())
            ->get(route('admin.payout-accounts.index', ['status' => 'verified']))
            ->assertOk();
        $props = $response->viewData('page')['props']['accounts']['data'];
        $this->assertCount(1, $props);
        $this->assertSame($verified->vendorProfile->business_name, $props[0]['business_name']);
        $this->assertArrayNotHasKey('account_number', $props[0]);

        $json = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString('123456789012', $json);
    }

    // ---- Withdrawal gate ---------------------------------------------------

    public function test_full_eligibility_allows_withdrawal_with_snapshot(): void
    {
        $vendor = $this->fullVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])
            ->assertRedirect();

        $request = VendorWithdrawalRequest::first();
        $account = $vendor->vendorProfile->payoutAccount;
        $this->assertSame($account->id, (int) $request->payout_account_id);
        $this->assertSame('bank', $request->payout_method);
        $this->assertSame('XXXXXXXX9012', $request->payout_destination_masked);
    }

    public function test_missing_payout_account_blocks_withdrawal(): void
    {
        $vendor = $this->kycVerifiedVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, VendorWithdrawalRequest::count());
    }

    public function test_pending_payout_account_blocks_withdrawal(): void
    {
        $vendor = $this->kycVerifiedVendor();
        $this->payoutAccounts()->save($vendor->vendorProfile, $this->bankData());
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $response = $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00']);
        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, VendorWithdrawalRequest::count());

        $this->actingAs($vendor)->get(route('vendor.finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('eligibility.reason', 'Payout details are pending verification.'));
    }

    public function test_rejected_payout_account_blocks_with_reason(): void
    {
        $vendor = $this->kycVerifiedVendor();
        $account = $this->payoutAccounts()->save($vendor->vendorProfile, $this->bankData());
        $this->payoutAccounts()->reject($account, $this->admin(), 'Blurry proof');
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($vendor)->get(route('vendor.finance.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('eligibility.reason', 'Payout details were rejected: Blurry proof. Update them to request withdrawals.'));
    }

    public function test_old_withdrawal_keeps_historical_destination(): void
    {
        $vendor = $this->fullVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])
            ->assertRedirect();
        $request = VendorWithdrawalRequest::first();
        $this->assertSame('XXXXXXXX9012', $request->payout_destination_masked);

        // Replace payout details afterwards: history is untouched.
        $this->actingAs($vendor)->post(route('vendor.payout-account.store'), $this->upiData());
        $this->assertSame('XXXXXXXX9012', $request->refresh()->payout_destination_masked);
    }

    // ---- Partial refunds -----------------------------------------------------

    public function test_admin_can_record_partial_refund_with_prorated_reversal(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($this->admin())->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '2000.00',
            'reason' => 'Partial service issue',
            'reference' => 'test-ref-1',
        ])->assertRedirect();

        $refund = BookingRefund::first();
        $this->assertSame('2000.00', $refund->amount);
        $this->assertSame('1800.00', $refund->vendor_reversal_amount);
        $this->assertSame('processed', $refund->status->value);

        // Exactly one reversal, linked both ways.
        $reversals = VendorLedgerEntry::where('type', LedgerEntryType::RefundReversal->value)->get();
        $this->assertCount(1, $reversals);
        $this->assertSame($refund->id, (int) $reversals->first()->booking_refund_id);
        $this->assertSame("refund-reversal:{$refund->id}", $reversals->first()->reference);

        // Snapshots immutable.
        $booking = $booking->refresh();
        $this->assertSame('10000.00', $booking->gross_amount);
        $this->assertSame('9000.00', $booking->vendor_earning_amount);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
    }

    public function test_refund_amount_validation(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        foreach (['0', '-50'] as $bad) {
            $this->actingAs($this->admin())->post(route('admin.bookings.refunds.store', $booking), [
                'amount' => $bad, 'reason' => 'Bad amount',
            ])->assertSessionHasErrors('amount');
        }

        $this->actingAs($this->admin())->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '10000.01', 'reason' => 'Too much',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->admin())->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '100.00', 'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, BookingRefund::count());
    }

    public function test_multiple_partial_refunds_and_cumulative_cap(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '2000.00', 'reason' => 'First',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '3000.00', 'reason' => 'Second',
        ])->assertRedirect();

        $this->assertSame('5000.00', $this->refunds()->processedRefundTotal($booking));
        $this->assertSame('5000.00', $this->refunds()->refundableRemaining($booking));
        $this->assertSame('4500.00', $this->refunds()->reversedVendorTotal($booking));
        $this->assertSame('4500.00', $this->refunds()->remainingVendorEarning($booking));

        // Cumulative cap: 5000.01 over the 5000 remainder fails; exact remainder works.
        $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '5000.01', 'reason' => 'Over cap',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '5000.00', 'reason' => 'Final',
        ])->assertRedirect();

        $this->assertSame('10000.00', $this->refunds()->processedRefundTotal($booking));
        $this->assertSame('0.00', $this->refunds()->refundableRemaining($booking));
        // Exact reconciliation: cumulative reversals equal the snapshot.
        $this->assertSame('9000.00', $this->refunds()->reversedVendorTotal($booking));
        $this->assertSame('0.00', $this->refunds()->remainingVendorEarning($booking));
        // Fully refunded ⇒ payment reflects reality.
        $this->assertSame(PaymentStatus::Refunded, $booking->refresh()->payment_status);
    }

    public function test_reversal_uses_snapshot_ratio_not_current_setting(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        Setting::setValue('platform_commission_percentage', '20');

        $this->actingAs($this->admin())->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '2000.00', 'reason' => 'Ratio check',
        ])->assertRedirect();

        // Still 1800 (10% snapshot), not 1600 (20% current).
        $this->assertSame('1800.00', BookingRefund::first()->vendor_reversal_amount);
    }

    public function test_rounding_reconciles_exactly_on_final_refund(): void
    {
        Setting::setValue('platform_commission_percentage', '33.33');

        $vendor = $this->vendor();
        // 2 × 5000 = 10000 gross @ 33.33% → commission 3333.00, earning 6667.00.
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $this->assertSame('6667.00', $booking->vendor_earning_amount);

        $admin = $this->admin();
        foreach ([['100.00', 'R1'], ['300.00', 'R2'], ['9600.00', 'R3']] as [$amount, $reason]) {
            $this->actingAs($admin)->post(route('admin.bookings.refunds.store', $booking), [
                'amount' => $amount, 'reason' => $reason,
            ])->assertRedirect();
        }

        $this->assertSame('10000.00', $this->refunds()->processedRefundTotal($booking));
        // No 0.01 drift: cumulative reversals equal the snapshot exactly.
        $this->assertSame('6667.00', $this->refunds()->reversedVendorTotal($booking));
        $this->assertSame(3, VendorLedgerEntry::where('type', LedgerEntryType::RefundReversal->value)->count());
    }

    public function test_refund_retry_with_same_reference_returns_original(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $admin = $this->admin();

        $first = $this->refunds()->recordRefund($booking, '2000.00', 'Retry check', $admin, null, 'idem-key-1');
        $second = $this->refunds()->recordRefund($booking, '2000.00', 'Retry check', $admin, null, 'idem-key-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BookingRefund::count());
        $this->assertSame(1, VendorLedgerEntry::where('type', LedgerEntryType::RefundReversal->value)->count());
    }

    public function test_full_refund_via_record_path(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($this->admin())->post(route('admin.bookings.refunds.store', $booking), [
            'amount' => '10000.00', 'reason' => 'Full refund',
        ])->assertRedirect();

        $this->assertSame('9000.00', BookingRefund::first()->vendor_reversal_amount);
        $this->assertSame(PaymentStatus::Refunded, $booking->refresh()->payment_status);
        $this->assertSame('0.00', $this->ledger()->balances($vendor->vendorProfile->id)['available_balance']);
    }

    public function test_legacy_full_reversal_blocks_later_double_reverse(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        // Legacy path first (payment status direct to refunded).
        $this->bookings()->markPayment($booking, PaymentStatus::Refunded);
        $this->assertSame('reversed', $this->ledger()->ledgerStateForBooking($booking->refresh()));

        // A later partial record still books the customer refund but adds no
        // second vendor reversal (earning already fully reversed).
        $booking->update(['payment_status' => PaymentStatus::Paid->value]);
        $this->actingAs($this->admin())->post(route('admin.bookings.refunds.store', $booking->refresh()), [
            'amount' => '1000.00', 'reason' => 'Post-legacy partial',
        ])->assertRedirect();

        $this->assertSame('0.00', BookingRefund::first()->vendor_reversal_amount);
        $this->assertSame(1, VendorLedgerEntry::where('type', LedgerEntryType::RefundReversal->value)->count());
    }

    public function test_partials_stand_down_legacy_mark_payment_path(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->refunds()->recordRefund($booking, '2000.00', 'Partial first', $this->admin());

        $before = VendorLedgerEntry::count();
        $this->bookings()->markPayment($booking->refresh(), PaymentStatus::Refunded);

        // No legacy full reversal appended on top of the partial.
        $this->assertSame($before, VendorLedgerEntry::count());
        $this->assertSame(PaymentStatus::Refunded, $booking->refresh()->payment_status);
    }

    // ---- Visibility ----------------------------------------------------------

    public function test_customer_sees_own_refund_info(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);
        $this->refunds()->recordRefund($booking, '2000.00', 'Partial issue', $this->admin());

        $this->actingAs($customer)->get(route('account.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('refunds', 1)
                ->where('refunds.0.amount', '2000.00')
                ->where('refunds.0.reason', 'Partial issue'));
    }

    public function test_vendor_sees_refund_financial_impact(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $this->refunds()->recordRefund($booking, '2000.00', 'Partial issue', $this->admin());

        $response = $this->actingAs($vendor)->get(route('vendor.bookings.show', $booking));
        $response->assertOk();

        $props = $response->viewData('page')['props']['booking'];
        $this->assertSame('partially_reversed', $props['ledger_state']);
        $this->assertSame('2000.00', $props['refund_impact']['refunded_total']);
        $this->assertSame('1800.00', $props['refund_impact']['earning_reversed']);
        $this->assertSame('7200.00', $props['refund_impact']['earning_remaining']);
    }

    public function test_unrelated_vendor_and_guest_refund_visibility(): void
    {
        // Guest first: actingAs() persists within a test.
        $vendorA = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendorA->vendorProfile));
        $this->get(route('account.bookings.show', $booking))->assertRedirect(route('login'));

        $vendorB = $this->vendor();
        $this->actingAs($vendorB)->get(route('vendor.bookings.show', $booking))->assertForbidden();
    }

    public function test_admin_booking_detail_shows_refund_context(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $this->refunds()->recordRefund($booking, '2000.00', 'Partial issue', $this->admin());

        $this->actingAs($this->admin())->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ledgerState', 'partially_reversed')
                ->where('refundSummary.refunded_total', '2000.00')
                ->where('refundSummary.refundable_remaining', '8000.00')
                ->where('refundSummary.vendor_reversed_total', '1800.00')
                ->where('refundSummary.vendor_earning_remaining', '7200.00'));
    }

    // ---- Adjustments -----------------------------------------------------------

    public function test_admin_adjustment_credit_and_debit_via_http(): void
    {
        $vendor = $this->fullVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $profile = $vendor->vendorProfile;

        $this->actingAs($this->admin())->post(route('admin.vendor-finances.adjustments.store', $profile), [
            'type' => 'adjustment_credit', 'amount' => '500.00', 'reason' => 'Goodwill', 'confirm' => true,
        ])->assertRedirect();
        $this->assertSame('9500.00', $this->ledger()->balances($profile->id)['available_balance']);

        $this->actingAs($this->admin())->post(route('admin.vendor-finances.adjustments.store', $profile), [
            'type' => 'adjustment_debit', 'amount' => '200.00', 'reason' => 'Correction', 'confirm' => true,
        ])->assertRedirect();
        $this->assertSame('9300.00', $this->ledger()->balances($profile->id)['available_balance']);

        $this->assertSame(2, VendorLedgerEntry::whereIn('type', [
            LedgerEntryType::AdjustmentCredit->value, LedgerEntryType::AdjustmentDebit->value,
        ])->count());
    }

    public function test_adjustment_requires_reason_and_confirm(): void
    {
        $vendor = $this->fullVendor();
        $profile = $vendor->vendorProfile;

        $this->actingAs($this->admin())->post(route('admin.vendor-finances.adjustments.store', $profile), [
            'type' => 'adjustment_credit', 'amount' => '100.00', 'reason' => '', 'confirm' => true,
        ])->assertSessionHasErrors('reason');

        $this->actingAs($this->admin())->post(route('admin.vendor-finances.adjustments.store', $profile), [
            'type' => 'adjustment_credit', 'amount' => '100.00', 'reason' => 'No confirm',
        ])->assertSessionHasErrors('confirm');

        $this->assertSame(0, VendorLedgerEntry::count());
    }

    public function test_vendor_cannot_create_adjustment(): void
    {
        $vendor = $this->fullVendor();

        $this->actingAs($vendor)->post(route('admin.vendor-finances.adjustments.store', $vendor->vendorProfile), [
            'type' => 'adjustment_credit', 'amount' => '9999.00', 'reason' => 'Forge', 'confirm' => true,
        ])->assertForbidden();

        $this->actingAs($vendor)->get(route('admin.vendor-finances.show', $vendor->vendorProfile))->assertForbidden();
        $this->assertSame(0, VendorLedgerEntry::count());
    }

    public function test_ledger_entries_have_no_mutation_routes(): void
    {
        $this->actingAs($this->admin())->patch('/admin/ledger-entries/1', [])->assertNotFound();
        $this->actingAs($this->admin())->delete('/admin/ledger-entries/1')->assertNotFound();
    }

    public function test_debit_may_go_negative_but_blocks_withdrawals(): void
    {
        $vendor = $this->fullVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $profile = $vendor->vendorProfile;

        $this->actingAs($this->admin())->post(route('admin.vendor-finances.adjustments.store', $profile), [
            'type' => 'adjustment_debit', 'amount' => '9500.00', 'reason' => 'Chargeback correction', 'confirm' => true,
        ])->assertRedirect();

        $balances = $this->ledger()->balances($profile->id);
        $this->assertSame('-500.00', $balances['available_balance']);

        // Withdrawals stay blocked while available <= 0.
        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '1000.00'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, VendorWithdrawalRequest::count());
    }

    public function test_admin_vendor_finance_detail(): void
    {
        $vendor = $this->fullVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($this->admin())->get(route('admin.vendor-finances.show', $vendor->vendorProfile))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('vendor.business_name', $vendor->vendorProfile->business_name)
                ->where('balances.recorded_earnings', '9000.00')
                ->where('kyc.verified', true)
                ->where('payoutAccount.masked_destination', 'XXXXXXXX9012')
                ->has('ledger.data', 1));
    }

    // ---- Provider seam -----------------------------------------------------------

    public function test_manual_processor_is_bound_and_settles_once(): void
    {
        $processor = app(PayoutProcessor::class);
        $this->assertInstanceOf(ManualPayoutProcessor::class, $processor);
        $this->assertSame('manual', $processor->key());

        $vendor = $this->fullVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $request = $this->ledger()->requestWithdrawal($vendor->vendorProfile, '2000.00', $vendor);
        $this->ledger()->approveWithdrawal($request, $this->admin());

        $this->actingAs($this->admin())->patch(route('admin.withdrawals.mark-paid', $request), [
            'payout_reference' => 'UTR-1',
        ])->assertRedirect();
        $countAfterFirst = VendorLedgerEntry::count();

        $this->actingAs($this->admin())->patch(route('admin.withdrawals.mark-paid', $request), [
            'payout_reference' => 'UTR-1',
        ])->assertRedirect();

        $this->assertSame($countAfterFirst, VendorLedgerEntry::count());
        $this->assertSame('UTR-1', $request->refresh()->payout_reference);
        $this->assertSame('2000.00', $this->ledger()->balances($vendor->vendorProfile->id)['paid_out']);

        // Historical destination snapshot survived settlement.
        $this->assertSame('XXXXXXXX9012', $request->refresh()->payout_destination_masked);
    }

    // ---- Regression -----------------------------------------------------------

    public function test_phase6_snapshot_and_phase7_earning_intact_after_partial_refund(): void
    {
        Setting::setValue('platform_commission_percentage', '10');

        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));
        $this->refunds()->recordRefund($booking, '2000.00', 'Partial', $this->admin());

        $booking = $booking->refresh();
        $this->assertSame('10000.00', $booking->gross_amount);
        $this->assertSame('10.00', $booking->platform_commission_percentage);
        $this->assertSame('1000.00', $booking->platform_commission_amount);
        $this->assertSame('9000.00', $booking->vendor_earning_amount);
        $this->assertSame(1, VendorLedgerEntry::where('type', LedgerEntryType::BookingEarning->value)->count());
    }

    public function test_legacy_mark_payment_full_refund_still_works(): void
    {
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->bookings()->markPayment($booking, PaymentStatus::Refunded);

        $this->assertSame(1, VendorLedgerEntry::where('type', LedgerEntryType::BookingEarning->value)->count());
        $this->assertSame(1, VendorLedgerEntry::where('type', LedgerEntryType::RefundReversal->value)->count());
        $this->assertSame('reversed', $this->ledger()->ledgerStateForBooking($booking->refresh()));
    }
}
