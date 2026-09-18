<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\KycDecided;
use App\Models\Booking;
use App\Models\City;
use App\Models\Review;
use App\Models\Setting;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorPayoutAccount;
use App\Models\VendorProfile;
use App\Models\VendorVerification;
use App\Models\VendorWithdrawalRequest;
use App\Notifications\AdminAlert;
use App\Notifications\BookingActivity;
use App\Notifications\VendorAccountActivity;
use App\Notifications\VendorBookingActivity;
use App\Services\BookingRefundService;
use App\Services\BookingService;
use App\Services\CancellationService;
use App\Services\PayoutAccountService;
use App\Services\ReviewService;
use App\Services\VendorApprovalService;
use App\Services\VendorLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 9: notifications + review eligibility + invoice polish.
 */
class NotificationReviewInvoiceTest extends TestCase
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

    protected function reviews(): ReviewService
    {
        return app(ReviewService::class);
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
            ['name' => 'Notify Guest', 'email' => 'notify@example.com', 'phone' => '9876543210'],
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

    protected function completedBooking(TourPackage $tour, User $user): Booking
    {
        $booking = $this->paidBooking($tour, $user);
        $this->bookings()->changeStatus($booking, BookingStatus::Confirmed);
        $this->bookings()->changeStatus($booking->refresh(), BookingStatus::Completed);

        return $booking->refresh();
    }

    // ---- Notification events -------------------------------------------------

    public function test_booking_creation_notifies_customer_and_vendor(): void
    {
        Notification::fake();

        $vendor = $this->vendor();
        $customer = $this->customer();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile), $customer);

        Notification::assertSentTo($customer, BookingActivity::class, fn ($n) => $n->kind === 'created');
        Notification::assertSentTo($vendor, VendorBookingActivity::class, fn ($n) => $n->kind === 'new_booking');
        Notification::assertNothingSentTo($this->admin());
    }

    public function test_admin_owned_booking_notifies_no_vendor(): void
    {
        Notification::fake();

        $vendor = $this->vendor();
        $tour = TourPackage::factory()->create(['price' => 5000, 'is_active' => true, 'moderation_status' => 'approved']);
        $this->book($tour, $this->customer());

        Notification::assertNotSentTo($vendor, VendorBookingActivity::class);
    }

    public function test_status_changes_notify_customer_and_vendor(): void
    {
        Notification::fake();

        $vendor = $this->vendor();
        $customer = $this->customer();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $this->bookings()->changeStatus($booking, BookingStatus::Confirmed);
        Notification::assertSentTo($customer, BookingActivity::class, fn ($n) => $n->kind === 'confirmed');

        $this->bookings()->changeStatus($booking->refresh(), BookingStatus::Cancelled);
        Notification::assertSentTo($customer, BookingActivity::class, fn ($n) => $n->kind === 'cancelled');
        Notification::assertSentTo($vendor, VendorBookingActivity::class, fn ($n) => $n->kind === 'cancelled');
    }

    public function test_completion_sends_review_invitation(): void
    {
        Notification::fake();

        $vendor = $this->vendor();
        $customer = $this->customer();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);
        $this->bookings()->changeStatus($booking, BookingStatus::Confirmed);
        $this->bookings()->changeStatus($booking->refresh(), BookingStatus::Completed);

        Notification::assertSentTo($customer, BookingActivity::class, fn ($n) => $n->kind === 'completed');
        Notification::assertSentTo($customer, BookingActivity::class, fn ($n) => $n->kind === 'review_invitation');
    }

    public function test_cancellation_decision_notifies_customer(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $cancellation = app(CancellationService::class)->request($booking, $customer, 'Plans changed');
        app(CancellationService::class)->approve($cancellation, $this->admin());

        Notification::assertSentTo($customer, BookingActivity::class, fn ($n) => $n->kind === 'cancellation_approved');
    }

    public function test_refund_notifies_customer_and_vendor(): void
    {
        Notification::fake();

        $vendor = $this->vendor();
        $customer = $this->customer();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);
        $this->refunds()->recordRefund($booking, '2000.00', 'Partial issue', $this->admin());

        Notification::assertSentTo($customer, BookingActivity::class, fn ($n) => $n->kind === 'refunded');
        Notification::assertSentTo($vendor, VendorBookingActivity::class, fn ($n) => $n->kind === 'refunded');
    }

    public function test_application_approval_and_rejection_notify_applicant(): void
    {
        Notification::fake();

        $applicant = $this->customer();
        $application = VendorApplication::factory()->create(['user_id' => $applicant->id]);
        app(VendorApprovalService::class)->approve($application, $this->admin()->id);

        Notification::assertSentTo($applicant, VendorAccountActivity::class, fn ($n) => $n->kind === 'application_approved');

        $applicant2 = $this->customer();
        $application2 = VendorApplication::factory()->create(['user_id' => $applicant2->id]);
        app(VendorApprovalService::class)->reject($application2, $this->admin()->id, 'Incomplete docs');

        Notification::assertSentTo($applicant2, VendorAccountActivity::class, fn ($n) => $n->kind === 'application_rejected');
    }

    public function test_application_submission_notifies_applicant_and_admins(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('vendor.application.store'), [
            'business_name' => 'Test Travels',
            'entity_type' => 'individual',
            'phone' => '9876543210',
            'email' => 'apply@example.com',
            'address' => '1 Main St',
            'city' => 'Mathura',
            'state' => 'UP',
            'country_code' => 'IN',
            'consent' => true,
        ])->assertRedirect();

        Notification::assertSentTo($customer, VendorAccountActivity::class, fn ($n) => $n->kind === 'application_submitted');
        Notification::assertSentTo($admin, AdminAlert::class, fn ($n) => $n->kind === 'application_submitted');
    }

    public function test_kyc_decision_notifies_vendor(): void
    {
        Notification::fake();

        $vendor = $this->vendor();
        $verification = VendorVerification::factory()->create(['user_id' => $vendor->id]);

        event(new KycDecided($verification, 'verified'));

        Notification::assertSentTo($vendor, VendorAccountActivity::class, fn ($n) => $n->kind === 'kyc_verified');
    }

    public function test_tour_moderation_notifies_vendor_and_admins(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $vendor = $this->vendor();
        $tour = TourPackage::factory()->forVendor($vendor->vendorProfile)->draft()->create([
            'city_id' => City::factory()->create()->id,
            'duration_days' => 2,
            'price' => 5000,
            'overview' => 'A fine tour overview.',
        ]);

        $this->actingAs($vendor)->post(route('vendor.tours.submit', $tour))->assertRedirect();
        Notification::assertSentTo($admin, AdminAlert::class, fn ($n) => $n->kind === 'tour_submitted');

        $this->actingAs($admin)->post(route('admin.packages.approve', $tour->refresh()))->assertRedirect();
        Notification::assertSentTo($vendor, VendorAccountActivity::class, fn ($n) => $n->kind === 'tour_approved');
    }

    public function test_withdrawal_lifecycle_notifies_vendor_and_admin(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $vendor = $this->fullVendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile));

        $this->actingAs($vendor)->post(route('vendor.withdrawals.store'), ['amount' => '2000.00'])->assertRedirect();
        $request = VendorWithdrawalRequest::first();

        Notification::assertSentTo($vendor, VendorAccountActivity::class, fn ($n) => $n->kind === 'withdrawal_requested');
        Notification::assertSentTo($admin, AdminAlert::class, fn ($n) => $n->kind === 'withdrawal_requested');

        $this->actingAs($admin)->patch(route('admin.withdrawals.reject', $request), ['rejection_reason' => 'Docs pending'])->assertRedirect();
        Notification::assertSentTo($vendor, VendorAccountActivity::class, fn ($n) => $n->kind === 'withdrawal_rejected');
    }

    public function test_payout_decision_notifies_vendor_with_mask_only(): void
    {
        Notification::fake();

        $vendor = $this->kycVerifiedVendor();
        $account = app(PayoutAccountService::class)->save($vendor->vendorProfile, [
            'method' => 'bank',
            'account_holder_name' => 'Test Vendor',
            'bank_name' => 'Test Bank',
            'account_number' => '411111111111',
            'ifsc' => 'HDFC0001234',
        ]);
        app(PayoutAccountService::class)->verify($account, $this->admin());

        Notification::assertSentTo($vendor, VendorAccountActivity::class, function ($n) {
            if ($n->kind !== 'payout_verified') {
                return false;
            }

            return str_contains(json_encode($n->toArray($n)), 'XXXXXXXX1111')
                && ! str_contains(json_encode($n->toArray($n)), '411111111111');
        });
    }

    public function test_notification_payloads_carry_no_secrets(): void
    {
        $vendor = $this->fullVendor();
        $customer = $this->customer();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);
        $this->refunds()->recordRefund($booking, '2000.00', 'Partial issue', $this->admin());

        $json = json_encode(DatabaseNotification::all()->pluck('data'));
        $this->assertStringNotContainsString('411111111111', $json);
        $this->assertStringNotContainsString('password', strtolower($json));
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringContainsString($booking->booking_reference_id, $json);
    }

    public function test_action_urls_are_relative_base_safe(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $urls = DatabaseNotification::all()
            ->pluck('data.action_url')
            ->filter()
            ->all();

        $this->assertNotEmpty($urls);

        foreach ($urls as $url) {
            $this->assertStringStartsWith('/', $url);
            $this->assertStringNotContainsString('http', $url);
        }
    }

    // ---- Notification center ---------------------------------------------------

    public function test_cross_user_notifications_inaccessible(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $this->book($this->vendorTour($vendorA->vendorProfile), $this->customer());

        $notificationA = $vendorA->notifications()->firstOrFail();

        $this->actingAs($vendorB)->patch(route('notifications.read', $notificationA))->assertNotFound();
        $this->assertNull($notificationA->refresh()->read_at);
    }

    public function test_mark_read_all_and_unread_count(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $this->assertSame(1, $customer->unreadNotifications()->count());

        $notification = $customer->notifications()->firstOrFail();
        $this->actingAs($customer)->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->refresh()->read_at);

        $this->book($this->vendorTour($vendor->vendorProfile), $customer);
        $this->assertSame(1, $customer->unreadNotifications()->count());

        $this->actingAs($customer)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $customer->unreadNotifications()->count());

        $this->actingAs($customer)->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unreadCount', 0)
                ->has('notifications.data', 2));
    }

    public function test_guest_cannot_access_notification_center(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    // ---- Mail ---------------------------------------------------------------------

    public function test_mail_uses_site_branding_and_safe_links(): void
    {
        Setting::setValue('site_name', 'Test Yatra');
        Setting::setValue('contact_email', 'care@testyatra.example');

        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $mail = (new BookingActivity($booking, 'confirmed'))->toMail($customer);

        $this->assertStringContainsString('Test Yatra', $mail->subject);
        $this->assertStringContainsString($booking->booking_reference_id, $mail->subject);
        $this->assertStringContainsString('care@testyatra.example', $mail->render());
        $this->assertStringStartsWith(rtrim(config('app.url'), '/'), $mail->actionUrl);
        $this->assertStringContainsString("/account/bookings/{$booking->id}", $mail->actionUrl);
    }

    public function test_mail_respects_opt_out_but_keeps_database(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $customer->update(['notification_preferences' => ['booking' => false]]);
        $vendor = $this->vendor();
        $this->book($this->vendorTour($vendor->vendorProfile), $customer);

        Notification::assertSentTo($customer, BookingActivity::class, function ($n, $channels) {
            return $channels === ['database'];
        });
    }

    public function test_mail_channel_used_by_default(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $vendor = $this->vendor();
        $this->book($this->vendorTour($vendor->vendorProfile), $customer);

        Notification::assertSentTo($customer, BookingActivity::class, function ($n, $channels) {
            return in_array('mail', $channels) && in_array('database', $channels);
        });
    }

    public function test_code_base_path_in_mail_links(): void
    {
        config()->set('app.url', 'https://example.com/code');

        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $mail = (new BookingActivity($booking, 'confirmed'))->toMail($customer);

        $this->assertStringContainsString('https://example.com/code/account/bookings/', $mail->actionUrl);
    }

    // ---- Reviews ---------------------------------------------------------------------

    public function test_completed_booking_customer_can_review(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);
        $booking = $this->completedBooking($tour, $customer);

        $this->actingAs($customer)->post(route('review.store', $booking), [
            'rating' => 5,
            'comment' => 'A wonderful, peaceful tour.',
        ])->assertRedirect();

        $review = Review::firstOrFail();
        $this->assertSame($booking->id, (int) $review->booking_id);
        $this->assertSame($tour->id, (int) $review->package_id);
        $this->assertFalse($review->is_approved);
        $this->assertSame(5, $review->rating);
    }

    public function test_pending_and_cancelled_bookings_cannot_review(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);

        $pending = $this->paidBooking($tour, $customer);
        $this->actingAs($customer)->post(route('review.store', $pending), ['rating' => 5])
            ->assertSessionHasErrors('booking');

        $cancelled = $this->paidBooking($tour, $customer);
        $this->bookings()->changeStatus($cancelled, BookingStatus::Cancelled);
        $this->actingAs($customer)->post(route('review.store', $cancelled->refresh()), ['rating' => 4])
            ->assertSessionHasErrors('booking');

        $this->assertSame(0, Review::count());
    }

    public function test_wrong_customer_cannot_review(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->completedBooking($this->vendorTour($vendor->vendorProfile), $owner);

        // Stranger cannot even view the booking, let alone review it.
        $this->actingAs($stranger)->post(route('review.store', $booking), ['rating' => 5])
            ->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    public function test_wrong_tour_eligibility_is_false(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $tourA = $this->vendorTour($vendor->vendorProfile);
        $tourB = $this->vendorTour($vendor->vendorProfile);
        $this->completedBooking($tourA, $customer);

        $this->assertFalse($this->reviews()->eligibility($customer, $tourB)['can_review']);
        $this->assertTrue($this->reviews()->eligibility($customer, $tourA)['can_review']);
    }

    public function test_duplicate_booking_review_blocked(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->completedBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $this->actingAs($customer)->post(route('review.store', $booking), ['rating' => 5])->assertRedirect();
        $this->actingAs($customer)->post(route('review.store', $booking), ['rating' => 4])
            ->assertSessionHasErrors('booking');

        $this->assertSame(1, Review::count());
    }

    public function test_fully_refunded_booking_cannot_review(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->completedBooking($this->vendorTour($vendor->vendorProfile), $customer);
        $this->refunds()->recordRefund($booking, '10000.00', 'Full refund', $this->admin());

        $this->actingAs($customer)->post(route('review.store', $booking->refresh()), ['rating' => 5])
            ->assertSessionHasErrors('booking');

        $this->assertSame(0, Review::count());
    }

    public function test_verified_badge_only_for_booking_reviews(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);
        $booking = $this->completedBooking($tour, $customer);
        $this->reviews()->submitBookingReview($customer, $booking, ['rating' => 5, 'comment' => 'Verified stay.']);
        Review::query()->update(['is_approved' => true]);

        Review::create([
            'package_id' => $tour->id,
            'reviewer_name' => 'Guest Writer',
            'rating' => 4,
            'comment' => 'A guest perspective.',
            'is_approved' => true,
        ]);

        $response = $this->get(route('packages.show', $tour));
        $response->assertOk();

        $reviews = collect($response->viewData('page')['props']['reviews']['data']);
        $this->assertTrue((bool) $reviews->firstWhere('reviewer_name', $customer->name)['is_verified_booking']);
        $this->assertFalse((bool) $reviews->firstWhere('reviewer_name', 'Guest Writer')['is_verified_booking']);
        $this->assertArrayNotHasKey('booking_id', $reviews->firstWhere('reviewer_name', $customer->name));
    }

    public function test_account_booking_page_shows_review_cta_state(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->completedBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $this->actingAs($customer)->get(route('account.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reviewEligibility.can_review', true)
                ->where('reviewEligibility.booking_id', $booking->id));

        $this->reviews()->submitBookingReview($customer, $booking, ['rating' => 5]);

        $this->actingAs($customer)->get(route('account.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reviewEligibility.can_review', false)
                ->where('reviewEligibility.has_review', true));
    }

    // ---- Invoice ---------------------------------------------------------------------

    public function test_invoice_uses_snapshot_and_hides_commission(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $tour = $this->vendorTour($vendor->vendorProfile);
        $booking = $this->paidBooking($tour, $customer);

        $tour->update(['price' => 99999]);

        $response = $this->actingAs($customer)->get(route('booking.invoice', $booking));
        $response->assertOk();

        $receipt = $response->viewData('page')['props']['receipt'];
        $this->assertSame('10000.00', $receipt['pricing']['gross_amount']);
        $this->assertSame('10000.00', $receipt['pricing']['total_amount']);

        $json = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString('platform_commission_amount', $json);
        $this->assertStringNotContainsString('vendor_earning_amount', $json);
    }

    public function test_invoice_reflects_partial_refund_net(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);
        $this->refunds()->recordRefund($booking, '2000.00', 'Partial issue', $this->admin());

        $response = $this->actingAs($customer)->get(route('booking.invoice', $booking));
        $response->assertOk();

        $receipt = $response->viewData('page')['props']['receipt'];
        $this->assertSame('10000.00', $receipt['pricing']['gross_amount']);
        $this->assertSame('2000.00', $receipt['refunds']['total']);
        $this->assertSame('8000.00', $receipt['refunds']['net']);
        $this->assertCount(1, $receipt['refunds']['history']);
    }

    public function test_invoice_pdf_download_still_works(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendor->vendorProfile), $customer);

        $this->actingAs($customer)->get(route('booking.invoice.download', $booking))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_guest_signed_confirmation_preserved(): void
    {
        $vendor = $this->vendor();
        $booking = $this->book($this->vendorTour($vendor->vendorProfile));

        $url = URL::signedRoute('booking.confirmation', $booking);

        $this->get($url)->assertOk();
        $this->get(route('booking.confirmation', $booking))->assertForbidden();
    }

    public function test_second_vendor_cannot_access_invoice(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $booking = $this->paidBooking($this->vendorTour($vendorA->vendorProfile), $this->customer());

        $this->actingAs($vendorB)->get(route('booking.invoice', $booking))->assertForbidden();
    }

    // ---- Impersonation ---------------------------------------------------------------------

    public function test_impersonation_stays_policy_bound_for_notifications(): void
    {
        $admin = $this->admin();
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $this->book($this->vendorTour($vendorA->vendorProfile), $this->customer());
        $this->book($this->vendorTour($vendorB->vendorProfile), $this->customer());

        $notificationB = $vendorB->notifications()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $vendorA))->assertRedirect();

        // Sees own notifications only.
        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('notifications.data', 1));

        // Cannot touch the other vendor's notification or bookings.
        $this->patch(route('notifications.read', $notificationB))->assertNotFound();
        $otherBooking = $vendorB->vendorProfile->bookings()->firstOrFail();
        $this->get(route('vendor.bookings.show', $otherBooking))->assertForbidden();
    }
}
