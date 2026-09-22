<?php

namespace Tests\Feature\Taxi;

use App\Enums\TaxiBookingStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Notifications\CrmNotification;
use App\Services\TaxiBookingService;
use App\Services\TaxiDispatchRecommendationService;
use App\Services\TaxiRatingSummaryService;
use App\Services\TaxiReviewService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaxiReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    // ---- helpers ----------------------------------------------------

    protected function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('super-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin->fresh();
    }

    protected function makeVendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $vendor->id, 'is_active' => true]);

        return $vendor->fresh();
    }

    protected function makeCustomer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    protected function makeDriverUser(VendorProfile $profile, array $overrides = []): array
    {
        $user = User::factory()->create(['role' => 'customer']);
        $driver = Driver::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'user_id' => $user->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ], $overrides));

        return [$user, $driver];
    }

    /**
     * @return array{0: Driver, 1: Vehicle}
     */
    protected function fleetFor(VendorProfile $profile, array $driverOverrides = [], array $vehicleOverrides = []): array
    {
        $driver = Driver::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'availability_status' => 'available',
            'employment_status' => 'active',
            'is_active' => true,
        ], $driverOverrides));

        $vehicle = Vehicle::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'vehicle_type_id' => VehicleType::factory()->create()->id,
            'status' => 'available',
            'is_active' => true,
            'passenger_capacity' => 4,
        ], $vehicleOverrides));

        return [$driver, $vehicle];
    }

    protected function bookingFor(VendorProfile $profile, array $overrides = []): TaxiBooking
    {
        return TaxiBooking::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'status' => TaxiBookingStatus::Confirmed->value,
            'pickup_at' => now()->addDay(),
            'passenger_count' => 2,
            'currency' => 'INR',
            'total_amount' => 2500,
        ], $overrides));
    }

    protected function completedBooking(VendorProfile $profile, User $customer, ?Driver $driver = null, ?Vehicle $vehicle = null): TaxiBooking
    {
        $booking = $this->bookingFor($profile, ['customer_user_id' => $customer->id]);

        if ($driver && $vehicle) {
            app(TaxiBookingService::class)->assign($booking, $driver, $vehicle, $this->makeAdmin());
        }

        $booking->forceFill([
            'status' => TaxiBookingStatus::Completed->value,
            'completed_at' => now()->subHours(2),
        ])->save();

        return $booking->fresh();
    }

    protected function submit(TaxiBooking $booking, User $customer, array $input = []): TaxiReview
    {
        return app(TaxiReviewService::class)->submit(
            $booking, $customer, array_merge(['overall_rating' => 5], $input)
        );
    }

    // ---- 1 reviews disabled blocks review ----------------------------

    public function test_reviews_disabled_blocks_review(): void
    {
        Setting::setValue('taxi.reviews.enabled', '0');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $check = app(TaxiReviewService::class)->eligibility($booking, $customer);
        $this->assertFalse($check['eligible']);

        try {
            $this->submit($booking, $customer);
            $this->fail('Disabled reviews must refuse submission.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('review', $e->errors());
        }
    }

    // ---- 2 completed own booking is eligible --------------------------

    public function test_completed_own_booking_is_eligible(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $check = app(TaxiReviewService::class)->eligibility($booking, $customer);

        $this->assertTrue($check['eligible']);
        $this->assertNull($check['reason']);
    }

    // ---- 3 incomplete booking not eligible ----------------------------

    public function test_incomplete_booking_is_not_eligible(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();

        foreach ([TaxiBookingStatus::Confirmed->value, TaxiBookingStatus::DriverAssigned->value, TaxiBookingStatus::EnRoute->value, TaxiBookingStatus::PassengerOnBoard->value] as $status) {
            $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id, 'status' => $status]);
            $check = app(TaxiReviewService::class)->eligibility($booking, $customer);
            $this->assertFalse($check['eligible'], "Status {$status} must not be reviewable.");
        }
    }

    // ---- 4 cancelled booking not eligible ------------------------------

    public function test_cancelled_booking_is_not_eligible(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();

        foreach ([TaxiBookingStatus::Cancelled->value, TaxiBookingStatus::NoShow->value] as $status) {
            $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id, 'status' => $status]);
            $check = app(TaxiReviewService::class)->eligibility($booking, $customer);
            $this->assertFalse($check['eligible'], "Status {$status} must not be reviewable.");
        }
    }

    // ---- 5 foreign customer cannot review booking -----------------------

    public function test_foreign_customer_cannot_review_booking(): void
    {
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $owner);

        $response = $this->actingAs($intruder)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 5,
        ]);

        $response->assertNotFound();
        $this->assertSame(0, TaxiReview::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 6 tracking token cannot submit review --------------------------

    public function test_tracking_token_cannot_submit_review(): void
    {
        $response = $this->post('/taxi/track/AbCdEfGhIjKlMnOpQrStUvWxYz12/reviews', [
            'overall_rating' => 5,
        ]);

        $response->assertNotFound();
    }

    // ---- 7 valid overall rating accepted ---------------------------------

    public function test_valid_overall_rating_is_accepted(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $response = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 4,
            'driver_rating' => 5,
            'comment' => 'Smooth ride to the temple.',
        ]);

        $response->assertRedirect();

        $review = TaxiReview::where('taxi_booking_id', $booking->id)->firstOrFail();
        $this->assertSame(4, (int) $review->overall_rating);
        $this->assertSame(5, (int) $review->driver_rating);
        $this->assertSame('Smooth ride to the temple.', $review->comment);
        $this->assertNotNull($review->submitted_at);
    }

    // ---- 8 rating below 1 rejected ----------------------------------------

    public function test_rating_below_1_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $response = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 0,
        ]);

        $response->assertSessionHasErrors('overall_rating');
        $this->assertSame(0, TaxiReview::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 9 rating above 5 rejected ------------------------------------------

    public function test_rating_above_5_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $response = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 6,
        ]);

        $response->assertSessionHasErrors('overall_rating');
        $this->assertSame(0, TaxiReview::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 10 optional category ratings validated ----------------------------------

    public function test_optional_category_ratings_are_validated(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $response = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 4,
            'punctuality_rating' => 9,
        ]);

        $response->assertSessionHasErrors('punctuality_rating');

        $ok = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 4,
            'driver_rating' => 4,
            'vehicle_rating' => 3,
            'service_rating' => 5,
            'punctuality_rating' => 5,
            'cleanliness_rating' => 4,
        ]);

        $ok->assertRedirect();
        $review = TaxiReview::where('taxi_booking_id', $booking->id)->firstOrFail();
        $this->assertSame(3, (int) $review->vehicle_rating);
        $this->assertSame(4, (int) $review->cleanliness_rating);
    }

    // ---- 11 comment length validated --------------------------------------------------

    public function test_comment_length_is_validated(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $response = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 5,
            'comment' => str_repeat('a', 1001),
        ]);

        $response->assertSessionHasErrors('comment');
        $this->assertSame(0, TaxiReview::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 12 HTML/script input safely handled ------------------------------------------------

    public function test_html_input_is_stripped_from_comment(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $review = $this->submit($booking, $customer, ['comment' => '<script>alert("x")</script>Great <b>trip</b>!']);

        $this->assertStringNotContainsString('<script>', (string) $review->comment);
        $this->assertStringNotContainsString('<b>', (string) $review->comment);
        $this->assertStringContainsString('Great', (string) $review->comment);
    }

    // ---- 13 one review per booking enforced ------------------------------------------------------

    public function test_one_review_per_booking_is_enforced(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $this->submit($booking, $customer);

        $check = app(TaxiReviewService::class)->eligibility($booking->fresh(), $customer);
        $this->assertFalse($check['eligible']);
        $this->assertSame(1, TaxiReview::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 14 duplicate submission rejected ----------------------------------------------------------------

    public function test_duplicate_submission_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $first = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), ['overall_rating' => 5]);
        $first->assertRedirect();

        try {
            $this->submit($booking->fresh(), $customer, ['overall_rating' => 1]);
            $this->fail('Second review for the same booking must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }

        $this->assertSame(1, TaxiReview::where('taxi_booking_id', $booking->id)->count());
        $this->assertSame(5, (int) TaxiReview::where('taxi_booking_id', $booking->id)->first()->overall_rating);
    }

    // ---- 15 vendor/driver/vehicle derived server-side ---------------------------------------------------------------

    public function test_relations_are_derived_server_side(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);

        $review = $this->submit($booking, $customer);

        $this->assertSame($vendor->vendorProfile->id, (int) $review->vendor_profile_id);
        $this->assertSame($driver->id, (int) $review->driver_id);
        $this->assertSame($vehicle->id, (int) $review->vehicle_id);
        $this->assertSame($customer->id, (int) $review->customer_user_id);
    }

    // ---- 16 forged driver id ignored/rejected ----------------------------------------------------------------------------------

    public function test_forged_driver_id_is_ignored(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $otherVendor = $this->makeVendor();
        [$driver] = $this->fleetFor($vendor->vendorProfile);
        [$foreignDriver] = $this->fleetFor($otherVendor->vendorProfile);
        [, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);

        $response = $this->actingAs($customer)->post(route('account.taxi.reviews.store', $booking, absolute: false), [
            'overall_rating' => 5,
            'driver_id' => $foreignDriver->id,
            'vendor_profile_id' => $otherVendor->vendorProfile->id,
        ]);

        $response->assertRedirect();

        $review = TaxiReview::where('taxi_booking_id', $booking->id)->firstOrFail();
        $this->assertSame($driver->id, (int) $review->driver_id);
        $this->assertSame($vendor->vendorProfile->id, (int) $review->vendor_profile_id);
    }

    // ---- 17 final reassigned driver gets review ------------------------------------------------------------------------------------------

    public function test_final_reassigned_driver_receives_review(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        [$driverA, $vehicleA] = $this->fleetFor($vendor->vendorProfile);
        [$driverB, $vehicleB] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id]);

        app(TaxiBookingService::class)->assign($booking, $driverA, $vehicleA, $admin);
        app(TaxiBookingService::class)->unassign($booking->fresh(), $admin);
        app(TaxiBookingService::class)->assign($booking->fresh(), $driverB, $vehicleB, $admin);
        $booking->forceFill(['status' => TaxiBookingStatus::Completed->value, 'completed_at' => now()])->save();

        $review = $this->submit($booking->fresh(), $customer);

        $this->assertSame($driverB->id, (int) $review->driver_id);
        $this->assertSame($vehicleB->id, (int) $review->vehicle_id);
    }

    // ---- 18 previous driver does not receive review -----------------------------------------------------------------------------------------------------

    public function test_previous_driver_has_no_review_attached(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        [$driverA, $vehicleA] = $this->fleetFor($vendor->vendorProfile);
        [$driverB, $vehicleB] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->bookingFor($vendor->vendorProfile, ['customer_user_id' => $customer->id]);

        app(TaxiBookingService::class)->assign($booking, $driverA, $vehicleA, $admin);
        app(TaxiBookingService::class)->unassign($booking->fresh(), $admin);
        app(TaxiBookingService::class)->assign($booking->fresh(), $driverB, $vehicleB, $admin);
        $booking->forceFill(['status' => TaxiBookingStatus::Completed->value, 'completed_at' => now()])->save();

        $this->submit($booking->fresh(), $customer);

        $this->assertSame(0, app(TaxiRatingSummaryService::class)->forDriver($driverA)['count']);
        $this->assertSame(0, TaxiReview::where('driver_id', $driverA->id)->count());
    }

    // ---- 19 moderation enabled creates pending -------------------------------------------------------------------------------------------------------------------------------

    public function test_moderation_enabled_creates_pending_review(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '1');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $review = $this->submit($booking, $customer);

        $this->assertSame(TaxiReview::STATUS_PENDING, $review->status);
        $this->assertNull($review->approved_at);
    }

    // ---- 20 moderation disabled auto-approves -------------------------------------------------------------------------------------------------------------------------------------------

    public function test_moderation_disabled_auto_approves(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        $review = $this->submit($booking, $customer);

        $this->assertSame(TaxiReview::STATUS_APPROVED, $review->status);
        $this->assertNotNull($review->approved_at);
    }

    // ---- 21 admin can approve ----------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_can_approve_review(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);
        $review = $this->submit($booking, $customer);

        $response = $this->actingAs($admin)->post(route('admin.taxi.reviews.moderate', $review, absolute: false), [
            'action' => 'approve',
            'moderation_note' => 'Looks genuine.',
        ]);

        $response->assertRedirect();

        $fresh = $review->fresh();
        $this->assertSame(TaxiReview::STATUS_APPROVED, $fresh->status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertSame($admin->id, (int) $fresh->moderated_by);
        $this->assertSame(5, (int) $fresh->overall_rating);
    }

    // ---- 22 admin can reject ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_admin_can_reject_review(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);
        $review = $this->submit($booking, $customer);

        $response = $this->actingAs($admin)->post(route('admin.taxi.reviews.moderate', $review, absolute: false), [
            'action' => 'reject',
            'moderation_note' => 'Spam.',
        ]);

        $response->assertRedirect();

        $fresh = $review->fresh();
        $this->assertSame(TaxiReview::STATUS_REJECTED, $fresh->status);
        $this->assertNotNull($fresh->rejected_at);
        $this->assertSame(5, (int) $fresh->overall_rating);
        $this->assertSame('Spam.', $fresh->moderation_note);
    }

    // ---- 23 rejected review excluded from aggregates ------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_rejected_review_excluded_from_aggregates(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);
        $review = $this->submit($booking, $customer);

        app(TaxiReviewService::class)->moderate($review, 'reject', $admin);

        $summary = app(TaxiRatingSummaryService::class)->forDriver($driver);
        $this->assertSame(0, $summary['count']);
        $this->assertNull($summary['overall_avg']);
    }

    // ---- 24 hidden review excluded from aggregates -----------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_hidden_review_excluded_from_aggregates(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);
        $review = $this->submit($booking, $customer);

        $this->assertSame(1, app(TaxiRatingSummaryService::class)->forDriver($driver)['count']);

        app(TaxiReviewService::class)->moderate($review, 'hide', $admin);

        $summary = app(TaxiRatingSummaryService::class)->forDriver($driver);
        $this->assertSame(0, $summary['count']);
        $this->assertNull($summary['overall_avg']);
    }

    // ---- 25 approved review included in driver average ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_approved_review_included_in_driver_average(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);
        $this->submit($booking, $customer, ['overall_rating' => 4, 'driver_rating' => 5]);

        $summary = app(TaxiRatingSummaryService::class)->forDriver($driver);

        $this->assertSame(1, $summary['count']);
        $this->assertSame(4.0, $summary['overall_avg']);
        $this->assertSame(5.0, $summary['driver_avg']);
    }

    // ---- 26 driver average calculated correctly ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_driver_average_calculated_correctly(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);

        foreach ([5, 4, 5] as $rating) {
            $customer = $this->makeCustomer();
            $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);
            $this->submit($booking, $customer, ['overall_rating' => $rating, 'driver_rating' => $rating]);
        }

        $summary = app(TaxiRatingSummaryService::class)->forDriver($driver);

        $this->assertSame(3, $summary['count']);
        $this->assertSame(4.7, $summary['overall_avg']);
        $this->assertSame(4.7, $summary['driver_avg']);
    }

    // ---- 27 vendor average calculated correctly --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_average_calculated_correctly(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);

        foreach ([5, 3] as $rating) {
            $customer = $this->makeCustomer();
            $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);
            $this->submit($booking, $customer, ['overall_rating' => $rating]);
        }

        $summary = app(TaxiRatingSummaryService::class)->forVendor($vendor->vendorProfile);

        $this->assertSame(2, $summary['count']);
        $this->assertSame(4.0, $summary['overall_avg']);
    }

    // ---- 28 rating distribution correct ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_rating_distribution_is_correct(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);

        foreach ([5, 5, 4, 3] as $rating) {
            $customer = $this->makeCustomer();
            $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);
            $this->submit($booking, $customer, ['overall_rating' => $rating]);
        }

        $summary = app(TaxiRatingSummaryService::class)->forVendor($vendor->vendorProfile);

        $this->assertSame([1 => 0, 2 => 0, 3 => 1, 4 => 1, 5 => 2], $summary['distribution']);
    }

    // ---- 29 vendor sees own reviews only ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_sees_own_reviews_only(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $customer = $this->makeCustomer();

        $own = $this->completedBooking($vendorA->vendorProfile, $customer);
        $this->submit($own, $customer);

        $foreignCustomer = $this->makeCustomer();
        $foreign = $this->completedBooking($vendorB->vendorProfile, $foreignCustomer);
        $this->submit($foreign, $foreignCustomer);

        $response = $this->actingAs($vendorA)->get(route('vendor.taxi.reviews.index', absolute: false));

        $response->assertOk();
        $response->assertSee($own->reference);
        $response->assertDontSee($foreign->reference);
    }

    // ---- 30 vendor cannot see foreign reviews -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_open_foreign_review(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->completedBooking($vendorA->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer);

        $this->actingAs($vendorB)->get(route('vendor.taxi.reviews.show', $review, absolute: false))->assertNotFound();
        $this->actingAs($vendorB)->post(route('vendor.taxi.reviews.reply', $review, absolute: false), ['reply' => 'Hijack'])->assertNotFound();
    }

    // ---- 31 vendor cannot delete negative review -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_delete_negative_review(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer, ['overall_rating' => 1]);

        $this->actingAs($vendor)->delete(route('vendor.taxi.reviews.show', $review, absolute: false))->assertStatus(405);

        try {
            $review->delete();
            $this->fail('Reviews must never be hard-deletable.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('taxi_reviews', ['id' => $review->id, 'overall_rating' => 1]);
    }

    // ---- 32 vendor can reply to own approved review ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_can_reply_to_own_approved_review(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer);

        $response = $this->actingAs($vendor)->post(route('vendor.taxi.reviews.reply', $review, absolute: false), [
            'reply' => 'Sorry for the delay — we are improving dispatch.',
        ]);

        $response->assertRedirect();

        $fresh = $review->fresh();
        $this->assertSame('Sorry for the delay — we are improving dispatch.', $fresh->vendor_reply);
        $this->assertNotNull($fresh->vendor_replied_at);
    }

    // ---- 33 vendor cannot reply to foreign review ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_vendor_cannot_reply_to_foreign_review(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->completedBooking($vendorA->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer);

        $this->actingAs($vendorB)->post(route('vendor.taxi.reviews.reply', $review, absolute: false), [
            'reply' => 'Hijack reply',
        ])->assertNotFound();

        $this->assertNull($review->fresh()->vendor_reply);
    }

    // ---- 34 reply disabled blocks vendor reply ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_reply_disabled_blocks_vendor_reply(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        Setting::setValue('taxi.reviews.allow_vendor_reply', '0');
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer);

        $response = $this->actingAs($vendor)->post(route('vendor.taxi.reviews.reply', $review, absolute: false), [
            'reply' => 'Should be blocked',
        ]);

        $response->assertSessionHasErrors('reply');
        $this->assertNull($review->fresh()->vendor_reply);
    }

    // ---- 35 driver sees own approved feedback ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_driver_sees_own_approved_feedback(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        [$driverUser, $driver] = $this->makeDriverUser($vendor->vendorProfile);
        [, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer(), $driver, $vehicle);
        $this->submit($booking, $booking->customer, ['overall_rating' => 5, 'comment' => 'Excellent driving.']);

        $response = $this->actingAs($driverUser)->get(route('driver.taxi.reviews.index', absolute: false));

        $response->assertOk();
        $response->assertSee($booking->reference);
        $response->assertSee('Excellent driving.');
    }

    // ---- 36 driver cannot see another driver's review data ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_driver_cannot_see_other_drivers_feedback(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        [$driverUserA] = $this->makeDriverUser($vendor->vendorProfile);
        [, $otherDriver] = $this->makeDriverUser($vendor->vendorProfile);
        [, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer(), $otherDriver, $vehicle);
        $this->submit($booking, $booking->customer, ['overall_rating' => 2, 'comment' => 'OtherDriverFeedbackMarker']);

        $response = $this->actingAs($driverUserA)->get(route('driver.taxi.reviews.index', absolute: false));

        $response->assertOk();
        $response->assertDontSee('OtherDriverFeedbackMarker');
        $response->assertDontSee($booking->reference);
    }

    // ---- 37 driver cannot moderate review ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_driver_cannot_moderate_review(): void
    {
        $vendor = $this->makeVendor();
        [$driverUser, $driver] = $this->makeDriverUser($vendor->vendorProfile);
        [, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer(), $driver, $vehicle);
        $review = $this->submit($booking, $booking->customer);

        $this->actingAs($driverUser)->post(route('admin.taxi.reviews.moderate', $review, absolute: false), [
            'action' => 'approve',
        ])->assertForbidden();

        $this->assertSame(TaxiReview::STATUS_PENDING, $review->fresh()->status);
    }

    // ---- 38 customer private data excluded ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_private_customer_data_excluded_from_portals(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $admin = $this->makeAdmin();
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Private Testperson',
            'email' => 'private-customer-12a12@example.com',
            'phone' => '919999888877',
        ]);
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, ...$this->fleetFor($vendor->vendorProfile));
        $booking->update(['pickup_address' => '221B Secret Lane Mathura', 'customer_phone' => '919999888877']);
        $review = $this->submit($booking, $customer, ['comment' => 'Fine trip.']);
        app(TaxiReviewService::class)->moderate($review, 'approve', $admin, 'InternalModerationNoteMarker');

        $vendorResponse = $this->actingAs($vendor)->get(route('vendor.taxi.reviews.show', $review, absolute: false));
        $vendorResponse->assertOk();
        $vendorResponse->assertDontSee('private-customer-12a12@example.com');
        $vendorResponse->assertDontSee('919999888877');
        $vendorResponse->assertDontSee('221B Secret Lane Mathura');
        $vendorResponse->assertDontSee('InternalModerationNoteMarker');

        [$driverUser] = $this->makeDriverUser($vendor->vendorProfile);
        $driverResponse = $this->actingAs($driverUser)->get(route('driver.taxi.reviews.index', absolute: false));
        $driverResponse->assertOk();
        $driverResponse->assertDontSee('private-customer-12a12@example.com');
        $driverResponse->assertDontSee('919999888877');
    }

    // ---- 39 low rating notification emitted ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_low_rating_emits_notifications(): void
    {
        Notification::fake();
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());

        $this->submit($booking, $booking->customer, ['overall_rating' => 2]);

        Notification::assertSentTo($vendor->fresh(), CrmNotification::class);
    }

    // ---- 40 normal rating does not emit low-rating alert ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_normal_rating_sends_no_low_rating_alert(): void
    {
        Notification::fake();
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());

        $this->submit($booking, $booking->customer, ['overall_rating' => 5]);

        Notification::assertNotSentTo($vendor->fresh(), CrmNotification::class, function (CrmNotification $notification): bool {
            return $notification->kind === 'taxi_review_low_rating';
        });
    }

    // ---- 41 moderation action audited ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_moderation_action_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer);

        $before = DB::table('activity_logs')->where('event', 'taxi_review.status_changed')->count();
        app(TaxiReviewService::class)->moderate($review, 'approve', $admin);

        $this->assertSame($before + 1, DB::table('activity_logs')->where('event', 'taxi_review.status_changed')->count());
    }

    // ---- 42 review submission audited ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_review_submission_is_audited(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());

        $before = DB::table('activity_logs')->where('event', 'taxi_review.created')->count();
        $this->submit($booking, $booking->customer);

        $this->assertSame($before + 1, DB::table('activity_logs')->where('event', 'taxi_review.created')->count());
    }

    // ---- 43 list/read does not audit-spam ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_review_reads_write_no_audit_events(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer);

        $before = DB::table('activity_logs')->count();
        $this->actingAs($admin)->get(route('admin.taxi.reviews.index', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('admin.taxi.reviews.show', $review, absolute: false))->assertOk();
        app(TaxiReviewService::class)->eligibility($booking->fresh(), $booking->customer);

        $this->assertSame($before, DB::table('activity_logs')->count());
    }

    // ---- 44 module disabled blocks review routes -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_disabled_module_blocks_review_routes(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);

        Setting::setValue('modules.taxi.enabled', '0');

        $this->actingAs($customer)->get(route('account.taxi.reviews.create', $booking, absolute: false))->assertNotFound();
        $this->actingAs($vendor)->get(route('vendor.taxi.reviews.index', absolute: false))->assertNotFound();
        $this->actingAs($this->makeAdmin())->get(route('admin.taxi.reviews.index', absolute: false))->assertNotFound();
    }

    // ---- 45 review window expiry enforced ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_review_window_expiry_is_enforced(): void
    {
        Setting::setValue('taxi.reviews.review_window_days', '30');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, [
            'customer_user_id' => $customer->id,
            'status' => TaxiBookingStatus::Completed->value,
            'completed_at' => now()->subDays(45),
        ]);

        $check = app(TaxiReviewService::class)->eligibility($booking, $customer);
        $this->assertFalse($check['eligible']);

        try {
            $this->submit($booking, $customer);
            $this->fail('Expired review windows must refuse submission.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }
    }

    // ---- 46 review inside window accepted -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_review_inside_window_is_accepted(): void
    {
        Setting::setValue('taxi.reviews.review_window_days', '30');
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->bookingFor($vendor->vendorProfile, [
            'customer_user_id' => $customer->id,
            'status' => TaxiBookingStatus::Completed->value,
            'completed_at' => now()->subDays(5),
        ]);

        $review = $this->submit($booking, $customer);

        $this->assertSame($booking->id, (int) $review->taxi_booking_id);
    }

    // ---- 47 completed booking with refund can remain reviewable -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_completed_booking_with_payments_remains_reviewable(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);
        $booking->payments()->create([
            'reference' => $booking->reference.'-P01',
            'amount' => '2500.00',
            'currency' => 'INR',
            'payment_method' => 'upi',
            'paid_at' => now(),
        ]);

        $check = app(TaxiReviewService::class)->eligibility($booking->fresh(), $customer);
        $this->assertTrue($check['eligible']);

        $review = $this->submit($booking->fresh(), $customer);
        $this->assertSame($booking->id, (int) $review->taxi_booking_id);
    }

    // ---- 48 review does not modify pricing snapshot -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_review_does_not_modify_pricing_snapshot(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer);
        $totalBefore = (string) $booking->total_amount;
        $snapshotBefore = $booking->pricing_snapshot;

        $this->submit($booking, $customer);

        $fresh = $booking->fresh();
        $this->assertSame($totalBefore, (string) $fresh->total_amount);
        $this->assertEquals($snapshotBefore, $fresh->pricing_snapshot);
    }

    // ---- 49 review does not modify driver earnings -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_review_does_not_modify_driver_earnings(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, $driver, $vehicle);

        $earning = TaxiDriverEarning::create([
            'earning_number' => 'TE-REVIEW-0001',
            'driver_id' => $driver->id,
            'vendor_profile_id' => $vendor->vendorProfile->id,
            'taxi_booking_id' => $booking->id,
            'currency' => 'INR',
            'calculation_type' => 'fixed',
            'gross_earning' => '300.00',
            'net_earning' => '300.00',
            'status' => 'payable',
            'earned_at' => now(),
        ]);

        $this->submit($booking, $customer);

        $this->assertDatabaseHas('taxi_driver_earnings', [
            'id' => $earning->id,
            'gross_earning' => '300.00',
            'net_earning' => '300.00',
            'status' => 'payable',
        ]);
        $this->assertSame(1, TaxiDriverEarning::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 50 review does not affect dispatch ranking ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_review_does_not_affect_dispatch_ranking(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer(), $driver, $vehicle);

        $probe = $this->bookingFor($vendor->vendorProfile, ['pickup_at' => now()->addHours(6)]);
        $before = app(TaxiDispatchRecommendationService::class)->recommend($probe);

        $this->submit($booking, $booking->customer, ['overall_rating' => 1]);

        $after = app(TaxiDispatchRecommendationService::class)->recommend($probe->fresh());

        $this->assertEquals($before, $after);
    }

    // ---- 51 cross-vendor moderation blocked -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_cross_vendor_moderation_is_blocked(): void
    {
        $admin = $this->makeAdmin();
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $booking = $this->completedBooking($vendorA->vendorProfile, $this->makeCustomer());
        $review = $this->submit($booking, $booking->customer);

        // Vendors have no moderation endpoint at all.
        $this->actingAs($vendorB)->post(route('admin.taxi.reviews.moderate', $review, absolute: false), [
            'action' => 'approve',
        ])->assertForbidden();

        // Vendor flag on a foreign review 404s.
        $this->actingAs($vendorB)->post(route('vendor.taxi.reviews.flag', $review, absolute: false), [
            'reason' => 'spam',
        ])->assertNotFound();

        $this->assertSame(TaxiReview::STATUS_PENDING, $review->fresh()->status);

        // Admin moderation still works.
        $this->actingAs($admin)->post(route('admin.taxi.reviews.moderate', $review, absolute: false), [
            'action' => 'approve',
        ])->assertRedirect();
    }

    // ---- 52 pagination/filtering works ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_pagination_and_filtering_work(): void
    {
        Notification::fake();
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        $vehicleType = VehicleType::factory()->create();

        for ($i = 0; $i < 16; $i++) {
            $customer = $this->makeCustomer();
            $booking = $this->bookingFor($vendor->vendorProfile, [
                'customer_user_id' => $customer->id,
                'vehicle_type_id' => $vehicleType->id,
                'status' => TaxiBookingStatus::Completed->value,
                'completed_at' => now()->subHours(2),
            ]);
            $this->submit($booking, $customer, ['overall_rating' => $i === 0 ? 2 : 5]);
        }

        $page1 = $this->actingAs($admin)->get(route('admin.taxi.reviews.index', absolute: false));
        $page1->assertOk();

        $page2 = $this->actingAs($admin)->get(route('admin.taxi.reviews.index', absolute: false).'?page=2');
        $page2->assertOk();

        $filtered = $this->actingAs($admin)->get(route('admin.taxi.reviews.index', absolute: false).'?rating=2');
        $filtered->assertOk();
        $filtered->assertSee('overall_rating&quot;:2', false);
        $filtered->assertDontSee('overall_rating&quot;:5', false);
    }

    // ---- 53 approved-only public aggregate excludes pending/rejected -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_public_aggregate_excludes_pending_and_rejected(): void
    {
        Setting::setValue('taxi.reviews.show_driver_rating_publicly', '1');
        Setting::setValue('taxi.reviews.minimum_reviews_for_public_average', '1');
        $admin = $this->makeAdmin();
        $vendor = $this->makeVendor();
        [$driver, $vehicle] = $this->fleetFor($vendor->vendorProfile);

        $pending = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer(), $driver, $vehicle);
        $this->submit($pending, $pending->customer, ['overall_rating' => 5]);

        $hidden = app(TaxiRatingSummaryService::class)->publicForDriver($driver);
        $this->assertFalse($hidden['visible']);

        $second = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer(), $driver, $vehicle);
        $approved = $this->submit($second, $second->customer, ['overall_rating' => 4]);
        app(TaxiReviewService::class)->moderate($approved, 'approve', $admin);

        $visible = app(TaxiRatingSummaryService::class)->publicForDriver($driver);
        $this->assertTrue($visible['visible']);
        $this->assertSame(1, $visible['count']);
        $this->assertSame(4.0, $visible['overall_avg']);
    }

    // ---- 54 duplicate concurrent review protected by DB constraint ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_db_constraint_blocks_duplicate_reviews(): void
    {
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $this->makeCustomer());

        TaxiReview::create([
            'taxi_booking_id' => $booking->id,
            'customer_user_id' => $booking->customer_user_id,
            'vendor_profile_id' => $booking->vendor_profile_id,
            'overall_rating' => 5,
            'status' => TaxiReview::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        try {
            TaxiReview::create([
                'taxi_booking_id' => $booking->id,
                'customer_user_id' => $booking->customer_user_id,
                'vendor_profile_id' => $booking->vendor_profile_id,
                'overall_rating' => 1,
                'status' => TaxiReview::STATUS_PENDING,
                'submitted_at' => now(),
            ]);
            $this->fail('Unique booking constraint must block the second row.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, TaxiReview::where('taxi_booking_id', $booking->id)->count());
    }

    // ---- 55 no internal notes/payment/tracking token exposed ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_no_internal_data_leaks_into_review_pages(): void
    {
        Setting::setValue('taxi.reviews.require_moderation', '0');
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $booking = $this->completedBooking($vendor->vendorProfile, $customer, ...$this->fleetFor($vendor->vendorProfile));
        $booking->payments()->create([
            'reference' => 'TX-PAY-SECRET12A12',
            'amount' => '2500.00',
            'currency' => 'INR',
            'payment_method' => 'upi',
            'paid_at' => now(),
        ]);
        $review = $this->submit($booking, $customer, ['comment' => 'Good.']);
        app(TaxiReviewService::class)->moderate($review, 'approve', $admin, 'SecretInternalNote12A12');

        foreach ([
            $this->actingAs($customer)->get(route('account.taxi.reviews.show', $review, absolute: false)),
            $this->actingAs($vendor)->get(route('vendor.taxi.reviews.show', $review, absolute: false)),
            $this->actingAs($admin)->get(route('admin.taxi.reviews.index', absolute: false)),
        ] as $response) {
            $response->assertOk();
            $response->assertDontSee('TX-PAY-SECRET12A12');
            $response->assertDontSee('token_hash');
        }

        $this->actingAs($customer)->get(route('account.taxi.reviews.show', $review, absolute: false))
            ->assertDontSee('SecretInternalNote12A12');
        $this->actingAs($vendor)->get(route('vendor.taxi.reviews.show', $review, absolute: false))
            ->assertDontSee('SecretInternalNote12A12');
    }
}
