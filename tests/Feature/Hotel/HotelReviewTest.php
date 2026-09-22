<?php

namespace Tests\Feature\Hotel;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomTypeStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ActivityLog;
use App\Models\HotelBooking;
use App\Models\HotelRatePlan;
use App\Models\HotelReview;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\HotelBookingService;
use App\Services\HotelRatingSummaryService;
use App\Services\HotelReviewService;
use App\Support\AdminNavigation;
use App\Support\HotelSettings;
use App\Support\ModuleManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HotelReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        app(ModuleManager::class)->setEnabled('hotels', true);
    }

    public function test_module_disabled_gates_review_submission_and_portals(): void
    {
        $booking = $this->completed();
        $review = HotelReview::factory()->forBooking($booking)->approved()->create();
        $admin = $this->admin();
        $vendor = $this->vendor();
        app(ModuleManager::class)->setEnabled('hotels', false);

        $this->actingAs($booking->user)->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertNotFound();
        $this->actingAs($admin)->get(route('admin.hotel.reviews.index'))->assertNotFound();
        $this->patchJson(route('admin.hotel.reviews.moderate', $review), ['action' => 'approve'])->assertNotFound();
        $this->actingAs($vendor)->get(route('vendor.hotel.reviews.index'))->assertNotFound();
        $this->get(route('hotels.show', $booking->property))->assertNotFound();
        $this->assertNotContains('hotel-reviews', collect(AdminNavigation::filteredFor($admin))->pluck('items')->flatten(1)->pluck('id')->all());
        $this->assertDatabaseCount('hotel_reviews', 1);
    }

    public function test_reviews_disabled_blocks_submission_and_hides_public_ratings(): void
    {
        $property = $this->property();
        $this->review($property);
        $booking = $this->completed($property);
        Setting::setValue('hotel.reviews.enabled', '0');

        $this->actingAs($booking->user)->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertNotFound();
        $this->page(route('hotels.show', $property))->assertJsonPath('props.reviews', null)
            ->assertJsonPath('props.reviewSummary', null)->assertJsonMissingPath('props.seo.structuredData.aggregateRating');
        $this->page(route('hotels.index'))->assertJsonPath('props.properties.data.0.reviews_count', 0)
            ->assertJsonPath('props.properties.data.0.rating_average', null);
        $this->assertDatabaseCount('hotel_reviews', 1);
    }

    public function test_guests_cannot_submit_or_open_the_customer_review_form(): void
    {
        $booking = $this->completed();

        $this->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertUnauthorized();
        $this->get(route('account.hotel-reviews.show', $booking))->assertRedirect();
        $this->assertDatabaseCount('hotel_reviews', 0);
    }

    public function test_completed_booking_submission_is_verified_and_server_derived(): void
    {
        $booking = $this->completed(overrides: ['rooms_count' => 3]);
        $foreign = $this->completed();
        $input = $this->payload() + [
            'property_id' => $foreign->property_id, 'user_id' => $foreign->user_id,
            'hotel_booking_id' => $foreign->id, 'verified_stay' => false,
            'status' => 'approved', 'published_at' => '2020-01-01', 'vendor_reply' => 'Forged response',
        ];

        $this->actingAs($booking->user)->post(route('account.hotel-reviews.store', $booking), $input)
            ->assertRedirect(route('account.hotel-reviews.show', $booking))->assertSessionHas('flash', 'Thank you. Your review is pending moderation.');

        $review = HotelReview::sole();
        $this->assertSame($booking->id, $review->hotel_booking_id);
        $this->assertSame($booking->property_id, $review->property_id);
        $this->assertSame($booking->user_id, $review->user_id);
        $this->assertTrue($review->verified_stay);
        $this->assertSame('pending', $review->status);
        $this->assertNull($review->published_at);
        $this->assertNull($review->vendor_reply);
        $this->assertSame(4, $review->overall_rating);
        $this->assertSame(5, $review->cleanliness_rating);
        $this->assertSame(0, $booking->property->fresh()->reviews_count);
        $this->assertDatabaseHas('activity_logs', ['event' => 'hotel_review.submitted', 'subject_id' => $review->id, 'actor_user_id' => $booking->user_id]);
    }

    public function test_foreign_booking_cannot_be_read_submitted_or_edited(): void
    {
        $booking = $this->completed();
        $review = HotelReview::factory()->forBooking($booking)->pending()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('account.hotel-reviews.show', $booking))->assertNotFound();
        $this->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertNotFound();
        $this->putJson(route('account.hotel-reviews.update', $booking), $this->payload(['comment' => 'An attempted replacement of someone else\'s review.']))->assertNotFound();
        $this->assertSame($review->comment, $review->fresh()->comment);
        $this->assertDatabaseCount('hotel_reviews', 1);
    }

    #[DataProvider('ineligibleStatuses')]
    public function test_non_completed_bookings_cannot_be_reviewed(string $status): void
    {
        $booking = $this->completed(overrides: ['status' => $status, 'completed_at' => null, 'check_in' => '2027-04-10', 'check_out' => '2027-04-12']);

        $this->actingAs($booking->user)->postJson(route('account.hotel-reviews.store', $booking), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('booking')
            ->assertJsonPath('errors.booking.0', 'Reviews open after your hotel stay is marked completed.');
        $this->assertFalse(app(HotelReviewService::class)->eligibility($booking, $booking->user)['can_review']);
        $this->assertDatabaseCount('hotel_reviews', 0);
    }

    public static function ineligibleStatuses(): array
    {
        return array_combine(['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'], array_map(fn (string $status): array => [$status], ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show']));
    }

    public function test_refunded_cancellation_cannot_be_reviewed(): void
    {
        $booking = $this->completed(overrides: ['status' => HotelBookingStatus::Cancelled, 'payment_status' => HotelPaymentStatus::Refunded, 'cancelled_at' => now()]);

        $this->actingAs($booking->user)->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertJsonValidationErrors('booking');
        $this->assertDatabaseCount('hotel_reviews', 0);
    }

    public function test_duplicate_submission_is_rejected_without_duplicate_audits(): void
    {
        $booking = $this->completed();
        $this->actingAs($booking->user)->post(route('account.hotel-reviews.store', $booking), $this->payload())->assertRedirect();

        $this->postJson(route('account.hotel-reviews.store', $booking), $this->payload(['overall_rating' => 1]))
            ->assertJsonValidationErrors('booking')->assertJsonPath('errors.booking.0', 'You have already reviewed this stay.');
        $this->assertDatabaseCount('hotel_reviews', 1);
        $this->assertSame(1, ActivityLog::where('event', 'hotel_review.submitted')->count());
        $this->assertSame(4, HotelReview::sole()->overall_rating);
    }

    public function test_database_uniqueness_protects_review_races_and_retries(): void
    {
        $review = $this->review();

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('hotel_reviews')->insert(Arr::except($review->getAttributes(), ['id']));
    }

    public function test_customer_can_review_distinct_completed_bookings(): void
    {
        $customer = User::factory()->create();
        $property = $this->property();
        $bookings = [$this->completed($property, $customer), $this->completed($property, $customer), $this->completed(customer: $customer)];

        foreach ($bookings as $booking) {
            $this->actingAs($customer)->post(route('account.hotel-reviews.store', $booking), $this->payload())->assertRedirect();
        }

        $this->assertSame(3, HotelReview::where('user_id', $customer->id)->count());
        $this->assertSame(2, HotelReview::where('property_id', $property->id)->count());
    }

    #[DataProvider('invalidReviewInputs')]
    public function test_invalid_review_payload_creates_no_review(string $field, mixed $value, string $message): void
    {
        $booking = $this->completed();

        $this->actingAs($booking->user)->postJson(route('account.hotel-reviews.store', $booking), $this->payload([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field)->assertJsonPath("errors.{$field}.0", $message);
        $this->assertDatabaseCount('hotel_reviews', 0);
        $this->assertSame(0, ActivityLog::where('event', 'like', 'hotel_review.%')->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function invalidReviewInputs(): array
    {
        $cases = [];

        foreach (['overall_rating', 'cleanliness_rating', 'location_rating', 'service_rating', 'comfort_rating', 'value_rating'] as $field) {
            $label = str_replace('_', ' ', $field);
            $cases[$field.' missing'] = [$field, null, "The {$label} field is required."];
            $cases[$field.' below minimum'] = [$field, 0, "The {$label} field must be between 1 and 5."];
            $cases[$field.' above maximum'] = [$field, 6, "The {$label} field must be between 1 and 5."];
            $cases[$field.' fractional'] = [$field, 4.5, "The {$label} field must be an integer."];
            $cases[$field.' unsafe array'] = [$field, ['rating' => 5], "The {$label} field must be an integer."];
        }

        return $cases + [
            'comment too short' => ['comment', str_repeat('a', 19), 'The comment field must be at least 20 characters.'],
            'comment too long' => ['comment', str_repeat('a', 2001), 'The comment field must not be greater than 2000 characters.'],
            'title too long' => ['title', str_repeat('界', 121), 'The title field must not be greater than 120 characters.'],
            'comment empty' => ['comment', '', 'The comment field is required.'],
            'comment whitespace' => ['comment', str_repeat(' ', 30), 'The comment field is required.'],
            'comment tags only' => ['comment', '<div><br><img src="x" onerror="alert(1)"></div>', 'The comment field is required.'],
            'comment meaningless' => ['comment', str_repeat('!', 30), 'Please describe your stay using words.'],
            'comment array' => ['comment', ['unsafe' => 'text'], 'The comment field must be a string.'],
        ];
    }

    public function test_html_is_stripped_before_storage_and_public_output(): void
    {
        $booking = $this->completed();
        Setting::setValue('hotel.reviews.moderation_enabled', '0');

        $this->actingAs($booking->user)->post(route('account.hotel-reviews.store', $booking), $this->payload([
            'title' => '<b>A restful stay</b>',
            'comment' => 'A <b>comfortable</b> stay with kind staff. <img src=x onerror="alert(1)">',
        ]))->assertRedirect();

        $review = HotelReview::sole();
        $this->assertSame('A restful stay', $review->title);
        $this->assertSame('A comfortable stay with kind staff.', $review->comment);
        $this->page(route('hotels.show', $booking->property))->assertJsonPath('props.reviews.data.0.comment', 'A comfortable stay with kind staff.');
    }

    public function test_comment_minimum_is_configurable_and_applies_after_sanitization(): void
    {
        $booking = $this->completed();
        Setting::setValue('hotel.reviews.minimum_comment_length', '50');

        $this->actingAs($booking->user)->postJson(route('account.hotel-reviews.store', $booking), $this->payload(['comment' => '<b>'.str_repeat('a', 49).'</b>']))
            ->assertJsonPath('errors.comment.0', 'The comment field must be at least 50 characters.');
        $this->post(route('account.hotel-reviews.store', $booking), $this->payload(['comment' => str_repeat('a', 50)]))->assertRedirect();
        $this->assertDatabaseCount('hotel_reviews', 1);
    }

    public function test_auto_approval_publishes_and_notifies_customer_and_current_vendor(): void
    {
        $vendor = $this->vendor();
        $booking = $this->completed($this->property($vendor));
        Setting::setValue('hotel.reviews.moderation_enabled', '0');

        $this->actingAs($booking->user)->post(route('account.hotel-reviews.store', $booking), $this->payload())->assertRedirect()
            ->assertSessionHas('flash', 'Your verified stay review is published.');

        $review = HotelReview::sole();
        $this->assertSame('approved', $review->status);
        $this->assertNotNull($review->published_at);
        $this->assertSame(1, $booking->property->fresh()->reviews_count);
        $this->assertSame('4.00', $booking->property->fresh()->rating_average);
        $this->assertSame(['hotel_review_approved'], $booking->user->notifications->pluck('data.kind')->all());
        $this->assertSame(['hotel_review_received'], $vendor->notifications->pluck('data.kind')->all());
        $this->assertSame('/account/hotel-bookings/'.$booking->id.'/review', $booking->user->notifications->first()->data['action_url']);
    }

    public function test_admin_approval_updates_publication_aggregate_audit_and_notifications_once(): void
    {
        $this->freezeTime();
        $vendor = $this->vendor();
        $review = $this->review($this->property($vendor), 'pending');
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.hotel.reviews.moderate', $review), ['action' => 'approve'])->assertRedirect();
        $this->patch(route('admin.hotel.reviews.moderate', $review), ['action' => 'approve'])->assertRedirect();

        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame(now()->toDateTimeString(), $review->published_at->toDateTimeString());
        $this->assertSame($admin->id, $review->moderated_by);
        $this->assertSame(1, $review->property->reviews_count);
        $this->assertSame(1, ActivityLog::where('event', 'hotel_review.approved')->count());
        $this->assertSame(['hotel_review_approved'], $review->user->notifications->pluck('data.kind')->all());
        $this->assertSame(['hotel_review_received'], $vendor->notifications->pluck('data.kind')->all());
    }

    public function test_rejection_removes_review_reply_and_rating_from_public_results(): void
    {
        $property = $this->property();
        $rejected = HotelReview::factory()->forBooking($this->completed($property))->fiveStar()->withVendorReply()->create();
        $remaining = $this->review($property, overrides: ['overall_rating' => 2]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.hotel.reviews.moderate', $rejected), ['action' => 'reject', 'reason' => '<b>Contains personal information</b>'])->assertRedirect();

        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('Contains personal information', $rejected->fresh()->rejection_reason);
        $this->assertNotNull($rejected->fresh()->vendor_reply);
        $this->assertNull($rejected->fresh()->publicPayload()['vendor_reply']);
        $this->assertSame('2.00', $property->fresh()->rating_average);
        $this->assertSame(1, $property->fresh()->reviews_count);
        $this->page(route('hotels.show', $property))->assertJsonCount(1, 'props.reviews.data')
            ->assertJsonPath('props.reviews.data.0.id', $remaining->id)
            ->assertJsonPath('props.reviewSummary.distribution.5', 0)
            ->assertJsonPath('props.seo.structuredData.aggregateRating.reviewCount', 1);
        $this->assertDatabaseHas('activity_logs', ['event' => 'hotel_review.rejected', 'subject_id' => $rejected->id]);
        $this->assertSame(['hotel_review_rejected'], $rejected->user->notifications->pluck('data.kind')->all());
    }

    public function test_admin_can_reapprove_a_rejected_review(): void
    {
        $review = $this->review(status: 'rejected');

        $this->actingAs($this->admin())->patch(route('admin.hotel.reviews.moderate', $review), ['action' => 'approve'])->assertRedirect();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertNull($review->fresh()->rejection_reason);
        $this->assertSame(1, $review->property->fresh()->reviews_count);
    }

    public function test_staff_review_access_and_mutations_require_their_permissions(): void
    {
        $staff = $this->staff([]);
        $viewer = $this->staff(['hotel.reviews.view']);
        $review = $this->review(status: 'pending');

        $this->actingAs($staff)->get(route('admin.hotel.reviews.index'))->assertForbidden();
        $this->get(route('admin.hotel.reviews.show', $review))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.hotel.reviews.index'))->assertOk();
        $this->patchJson(route('admin.hotel.reviews.moderate', $review), ['action' => 'approve'])->assertForbidden();
        $this->postJson(route('admin.hotel.reviews.reply', $review), ['reply' => 'A response without permission.'])->assertForbidden();
        $this->putJson(route('admin.hotel.reviews.reply.update', $review), ['reply' => 'A response without permission.'])->assertForbidden();
        $this->deleteJson(route('admin.hotel.reviews.reply.destroy', $review))->assertForbidden();
        $this->assertSame('pending', $review->fresh()->status);
        $this->assertNull($review->fresh()->vendor_reply);
    }

    #[DataProvider('nonModerators')]
    public function test_customers_and_vendors_cannot_moderate_reviews(string $role, string $action): void
    {
        $review = $this->review(status: 'pending');
        $actor = User::factory()->create(['role' => $role]);

        $this->actingAs($actor)->patchJson(route('admin.hotel.reviews.moderate', $review), ['action' => $action])->assertForbidden();
        $this->assertSame('pending', $review->fresh()->status);
    }

    public static function nonModerators(): array
    {
        return ['customer approve' => ['customer', 'approve'], 'customer reject' => ['customer', 'reject'], 'vendor approve' => ['vendor', 'approve'], 'vendor reject' => ['vendor', 'reject']];
    }

    public function test_customer_can_edit_pending_content_but_not_identity_or_moderation(): void
    {
        $review = $this->review(status: 'pending');
        $original = $review->only(['hotel_booking_id', 'property_id', 'user_id', 'verified_stay']);
        Setting::setValue('hotel.reviews.moderation_enabled', '0');

        $this->actingAs($review->user)->put(route('account.hotel-reviews.update', $review->booking), $this->payload([
            'overall_rating' => 2, 'comment' => 'We enjoyed the location but the room was quite noisy.',
        ]) + ['status' => 'approved', 'property_id' => 999999, 'user_id' => 999999, 'verified_stay' => false])->assertRedirect();

        $review->refresh();
        $this->assertSame(2, $review->overall_rating);
        $this->assertSame('We enjoyed the location but the room was quite noisy.', $review->comment);
        $this->assertSame($original, $review->only(array_keys($original)));
        $this->assertSame('pending', $review->status);
        $this->assertNull($review->published_at);
        $this->assertSame(0, $review->property->reviews_count);
        $this->assertDatabaseHas('activity_logs', ['event' => 'hotel_review.edited', 'subject_id' => $review->id]);
    }

    #[DataProvider('moderatedStatuses')]
    public function test_customer_cannot_edit_a_moderated_review(string $status): void
    {
        $review = $this->review(status: $status, overrides: ['overall_rating' => 3]);

        $this->actingAs($review->user)->putJson(route('account.hotel-reviews.update', $review->booking), $this->payload(['overall_rating' => 1]))
            ->assertJsonPath('errors.review.0', 'Only pending reviews can be edited.');
        $this->assertSame(3, $review->fresh()->overall_rating);
        $this->assertSame(0, ActivityLog::where('event', 'hotel_review.edited')->count());
    }

    public static function moderatedStatuses(): array
    {
        return ['approved' => ['approved'], 'rejected' => ['rejected']];
    }

    public function test_approved_only_aggregates_have_exact_averages_distribution_and_categories(): void
    {
        $property = $this->property();
        $ratings = [5, 5, 5, 4, 4, 4, 4];
        $cleanliness = [4, 3, 3, 3, 3, 3, 3];
        $comfort = [1, 2, 3, 4, 5, 4, 5];

        foreach ($ratings as $index => $rating) {
            $this->review($property, overrides: [
                'overall_rating' => $rating, 'cleanliness_rating' => $cleanliness[$index], 'location_rating' => 4,
                'service_rating' => $index === 6 ? 4 : 5, 'comfort_rating' => $comfort[$index], 'value_rating' => $index === 0 ? 5 : 4,
            ]);
        }

        $this->review($property, 'pending', ['overall_rating' => 1]);
        $this->review($property, 'rejected', ['overall_rating' => 1]);

        $summary = app(HotelRatingSummaryService::class)->forProperty($property->id);

        $this->assertSame(7, $summary['reviews_count']);
        $this->assertSame('4.43', $summary['rating_average']);
        $this->assertSame([5 => 3, 4 => 4, 3 => 0, 2 => 0, 1 => 0], $summary['distribution']);
        $this->assertSame(['cleanliness_rating' => '3.14', 'location_rating' => '4.00', 'service_rating' => '4.86', 'comfort_rating' => '3.43', 'value_rating' => '4.14'], $summary['category_averages']);
        $this->assertSame('4.43', $property->fresh()->rating_average);
        $this->page(route('hotels.show', $property))->assertJsonPath('props.seo.structuredData.aggregateRating', [
            '@type' => 'AggregateRating', 'ratingValue' => '4.43', 'reviewCount' => 7, 'bestRating' => 5, 'worstRating' => 1,
        ]);
    }

    public function test_zero_approved_reviews_have_null_average_and_no_rating_schema(): void
    {
        $property = $this->property();
        $this->review($property, 'pending');
        $this->review($property, 'rejected');

        $this->page(route('hotels.show', $property))->assertJsonPath('props.reviewSummary.reviews_count', 0)
            ->assertJsonPath('props.reviewSummary.rating_average', null)
            ->assertJsonPath('props.reviewSummary.category_averages.cleanliness_rating', null)
            ->assertJsonPath('props.reviewSummary.distribution.5', 0)
            ->assertJsonCount(0, 'props.reviews.data')->assertJsonMissingPath('props.seo.structuredData.aggregateRating');
    }

    public function test_vendor_can_add_and_update_one_reply_without_changing_the_review(): void
    {
        $vendor = $this->vendor();
        $review = $this->review($this->property($vendor), overrides: ['overall_rating' => 2]);
        $original = $review->only(['overall_rating', 'comment', 'user_id', 'verified_stay', 'status']);
        $path = route('vendor.hotel.reviews.reply', $review);

        $this->actingAs($vendor)->put($path, [
            'reply' => '<b>Thank you</b> for your honest feedback. We are improving the room.',
            'overall_rating' => 5, 'comment' => 'Vendor attempted rewrite', 'verified_stay' => false, 'status' => 'rejected',
        ])->assertRedirect();
        $this->put($path, ['reply' => 'Thank you for your feedback. The room issue has now been resolved.'])->assertRedirect();
        $this->put($path, ['reply' => 'Thank you for your feedback. The room issue has now been resolved.'])->assertRedirect();

        $review->refresh();
        $this->assertSame($original, $review->only(array_keys($original)));
        $this->assertSame($vendor->id, $review->replied_by);
        $this->assertSame('Thank you for your feedback. The room issue has now been resolved.', $review->vendor_reply);
        $this->assertSame(1, ActivityLog::where('event', 'hotel_review.reply_added')->count());
        $this->assertSame(1, ActivityLog::where('event', 'hotel_review.reply_updated')->count());
        $this->assertSame(['hotel_review_replied'], $review->user->notifications->pluck('data.kind')->all());
        $this->page(route('hotels.show', $review->property))->assertJsonPath('props.reviews.data.0.vendor_reply.comment', $review->vendor_reply);
        $this->assertSame('2.00', $review->property->fresh()->rating_average);
    }

    public function test_foreign_vendor_cannot_read_reply_or_filter_another_property(): void
    {
        $review = $this->review($this->property($this->vendor()));
        $intruder = $this->vendor();

        $this->actingAs($intruder)->get(route('vendor.hotel.reviews.show', $review))->assertNotFound();
        $this->putJson(route('vendor.hotel.reviews.reply', $review), ['reply' => 'Attempted unauthorized response.'])->assertNotFound();
        $this->get(route('vendor.hotel.reviews.index', ['property_id' => $review->property_id]))->assertNotFound();
        $this->page(route('vendor.hotel.reviews.index', ['vendor_profile_id' => $review->property->vendor_profile_id]))->assertJsonCount(0, 'props.reviews.data');
        $this->assertNull($review->fresh()->vendor_reply);
    }

    public function test_review_access_follows_current_property_ownership(): void
    {
        $oldVendor = $this->vendor();
        $newVendor = $this->vendor();
        $property = $this->property($oldVendor);
        $review = $this->review($property);
        $property->forceFill(['vendor_profile_id' => $newVendor->vendorProfile->id])->save();

        $this->actingAs($oldVendor)->get(route('vendor.hotel.reviews.show', $review))->assertNotFound();
        $this->actingAs($newVendor)->put(route('vendor.hotel.reviews.reply', $review), ['reply' => 'Thank you for staying with our property.'])->assertRedirect();
        $this->assertSame($newVendor->id, $review->fresh()->replied_by);
        $this->assertSame($oldVendor->vendorProfile->id, $review->booking->vendor_profile_id);
    }

    public function test_customer_cannot_create_an_official_property_reply(): void
    {
        $review = $this->review();

        $this->actingAs($review->user)->putJson(route('vendor.hotel.reviews.reply', $review), ['reply' => 'I cannot respond as the property.'])->assertForbidden();
        $this->postJson(route('admin.hotel.reviews.reply', $review), ['reply' => 'I cannot respond as the property.'])->assertForbidden();
        $this->assertNull($review->fresh()->vendor_reply);
    }

    #[DataProvider('unpublishedStatuses')]
    public function test_vendor_reply_requires_an_approved_review(string $status): void
    {
        $vendor = $this->vendor();
        $review = $this->review($this->property($vendor), $status);

        $this->actingAs($vendor)->putJson(route('vendor.hotel.reviews.reply', $review), ['reply' => 'Thank you for the feedback about your stay.'])
            ->assertJsonPath('errors.reply.0', 'Only approved reviews can receive a response.');
        $this->assertNull($review->fresh()->vendor_reply);
    }

    public static function unpublishedStatuses(): array
    {
        return ['pending' => ['pending'], 'rejected' => ['rejected']];
    }

    #[DataProvider('invalidReplies')]
    public function test_invalid_vendor_reply_is_not_saved(mixed $reply): void
    {
        $vendor = $this->vendor();
        $review = $this->review($this->property($vendor));

        $this->actingAs($vendor)->putJson(route('vendor.hotel.reviews.reply', $review), ['reply' => $reply])->assertJsonValidationErrors('reply');
        $this->assertNull($review->fresh()->vendor_reply);
        $this->assertSame(0, ActivityLog::where('event', 'hotel_review.reply_added')->count());
    }

    public static function invalidReplies(): array
    {
        return ['empty' => [''], 'tags only' => ['<br><img src=x>'], 'too short' => ['Thanks'], 'too long' => [str_repeat('a', 2001)], 'array' => [['text']], 'punctuation' => [str_repeat('!', 20)]];
    }

    public function test_vendor_replies_can_be_disabled(): void
    {
        $vendor = $this->vendor();
        $review = $this->review($this->property($vendor));
        Setting::setValue('hotel.reviews.vendor_replies_enabled', '0');

        $this->actingAs($vendor)->putJson(route('vendor.hotel.reviews.reply', $review), ['reply' => 'Thank you for reviewing your stay.'])
            ->assertJsonPath('errors.reply.0', 'Property responses are currently disabled.');
        $this->page(route('vendor.hotel.reviews.show', $review))->assertJsonPath('props.canReply', false);
        $this->assertNull($review->fresh()->vendor_reply);
    }

    public function test_admin_can_moderate_and_remove_vendor_reply_with_audit_retained(): void
    {
        $review = HotelReview::factory()->withVendorReply()->create();
        $moderator = $this->staff(['hotel.reviews.view', 'hotel.reviews.moderate']);

        $this->actingAs($moderator)->put(route('admin.hotel.reviews.reply.update', $review), ['reply' => 'An edited plain text official response.'])->assertRedirect();
        $this->assertSame('An edited plain text official response.', $review->fresh()->vendor_reply);
        $this->delete(route('admin.hotel.reviews.reply.destroy', $review))->assertRedirect();

        $this->assertNull($review->fresh()->vendor_reply);
        $this->assertNull($review->fresh()->replied_by);
        $this->assertDatabaseHas('activity_logs', ['event' => 'hotel_review.reply_updated', 'subject_id' => $review->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'hotel_review.reply_removed', 'subject_id' => $review->id]);
        $this->assertSame(1, $review->property->fresh()->reviews_count);
    }

    public function test_staff_reply_permission_can_add_but_cannot_overwrite_a_response(): void
    {
        $review = $this->review();
        $staff = $this->staff(['hotel.reviews.view', 'hotel.reviews.reply']);

        $this->actingAs($staff)->post(route('admin.hotel.reviews.reply', $review), ['reply' => 'Thank you for staying at our property.'])->assertRedirect();
        $this->postJson(route('admin.hotel.reviews.reply', $review), ['reply' => 'Attempt to overwrite a response.'])->assertUnprocessable();
        $this->assertSame('Thank you for staying at our property.', $review->fresh()->vendor_reply);
        $this->assertDatabaseHas('activity_logs', ['event' => 'hotel_review.reply_added', 'actor_user_id' => $staff->id]);
    }

    public function test_public_review_payload_is_privacy_safe_and_approved_only(): void
    {
        $customer = User::factory()->create(['name' => 'Narendra Sharma', 'email' => 'private-guest@example.test', 'phone' => '9876543210123']);
        $property = $this->property();
        $booking = $this->completed($property, $customer, ['booking_number' => 'HT-PRIVATE-BOOKING', 'pricing_snapshot' => ['payment_secret' => 'DO-NOT-EXPOSE']]);
        $review = HotelReview::factory()->forBooking($booking)->approved()->create();
        $this->review($property, 'pending', ['comment' => 'Pending review must stay private.']);
        $this->review($property, 'rejected', ['comment' => 'Rejected review must stay private.', 'rejection_reason' => 'Internal moderation context']);

        $response = $this->page(route('hotels.show', $property));
        $payload = $response->json('props.reviews.data');

        $this->assertCount(1, $payload);
        $this->assertSame(['id', 'customer_name', 'overall_rating', 'title', 'comment', 'verified_stay', 'review_date', 'vendor_reply'], array_keys($payload[0]));
        $this->assertSame($review->id, $payload[0]['id']);
        $this->assertSame('Narendra S.', $payload[0]['customer_name']);
        $this->assertTrue($payload[0]['verified_stay']);

        foreach (['private-guest@example.test', '9876543210123', 'HT-PRIVATE-BOOKING', 'DO-NOT-EXPOSE', 'Narendra Sharma', 'Internal moderation context', 'Pending review must stay private.', 'Rejected review must stay private.'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $response->getContent());
        }
    }

    #[DataProvider('publicNames')]
    public function test_public_names_are_shortened_or_safely_replaced(string $name, string $expected): void
    {
        $customer = User::factory()->create(['name' => $name]);
        $review = HotelReview::factory()->forBooking($this->completed(customer: $customer))->approved()->create();

        $this->assertSame($expected, $review->publicPayload()['customer_name']);
    }

    public static function publicNames(): array
    {
        return ['full name' => ['Narendra Sharma', 'Narendra S.'], 'single name' => ['Narendra', 'N.'], 'email name' => ['someone@example.test', 'Verified guest'], 'phone name' => ['9876543210', 'Verified guest'], 'markup name' => ['<script>alert(1)</script>', 'Verified guest'], 'unicode name' => ['José García', 'José G.']];
    }

    public function test_deleting_a_customer_preserves_review_and_uses_anonymous_display(): void
    {
        $review = $this->review();
        $review->user->delete();

        $this->assertNull($review->fresh()->user_id);
        $this->assertSame('Verified guest', $review->fresh()->publicPayload()['customer_name']);
        $this->assertSame(1, $review->property->reviews_count);
    }

    public function test_public_review_pagination_and_sorting_are_server_side_and_stable(): void
    {
        $property = $this->property();

        foreach (range(1, 13) as $index) {
            $this->review($property, overrides: ['overall_rating' => ($index % 5) + 1, 'published_at' => '2026-09-15 12:00:00']);
        }

        $this->review($property, 'pending', ['overall_rating' => 5]);
        $highest = $this->page(route('hotels.show', $property).'?review_sort=highest&per_page=100');
        $second = $this->page(route('hotels.show', $property).'?review_sort=highest&reviews_page=2');
        $lowest = $this->page(route('hotels.show', $property).'?review_sort=lowest');
        $recent = $this->page(route('hotels.show', $property));

        $highest->assertJsonCount(10, 'props.reviews.data')->assertJsonPath('props.reviews.total', 13)->assertJsonPath('props.reviews.data.0.overall_rating', 5);
        $second->assertJsonCount(3, 'props.reviews.data');
        $this->assertSame([], array_values(array_intersect(array_column($highest->json('props.reviews.data'), 'id'), array_column($second->json('props.reviews.data'), 'id'))));
        $lowest->assertJsonPath('props.reviews.data.0.overall_rating', 1);
        $recent->assertJsonPath('props.reviews.data.0.id', HotelReview::approved()->max('id'));
        $this->assertStringContainsString('review_sort=highest', $highest->json('props.reviews.next_page_url'));
    }

    public function test_invalid_public_sort_is_rejected(): void
    {
        $property = $this->property();

        $this->getJson(route('hotels.show', $property).'?review_sort=overall_rating%20desc%3B')->assertJsonValidationErrors('review_sort');
        $this->assertDatabaseCount('hotel_reviews', 0);
    }

    public function test_property_listing_uses_compact_fields_without_querying_reviews(): void
    {
        $property = $this->property();
        $this->review($property, overrides: ['overall_rating' => 5]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->page(route('hotels.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertJsonPath('props.properties.data.0.reviews_count', 1)
            ->assertJsonPath('props.properties.data.0.rating_average', '5.00')
            ->assertJsonMissingPath('props.properties.data.0.reviews');
        $this->assertFalse(collect($queries)->contains(fn (array $query): bool => str_contains(strtolower($query['query']), 'hotel_reviews')));
    }

    public function test_public_review_listing_eager_loads_customers_in_constant_queries(): void
    {
        $property = $this->property();
        $this->review($property);
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(HotelReviewService::class)->publicReviews($property)->toArray();
        $singleCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(1, 9) as $index) {
            $this->review($property);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = app(HotelReviewService::class)->publicReviews($property)->toArray();
        $pageCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(10, $page['data']);
        $this->assertSame($singleCount, $pageCount);
        $this->assertSame(3, $pageCount);
    }

    public function test_customer_bookings_expose_review_actions_and_existing_statuses(): void
    {
        $customer = User::factory()->create();
        $eligible = $this->completed(customer: $customer);
        $ineligible = $this->completed(customer: $customer, overrides: ['status' => HotelBookingStatus::Confirmed]);
        $pending = $this->completed(customer: $customer);
        $approved = $this->completed(customer: $customer);
        HotelReview::factory()->forBooking($pending)->pending()->create();
        HotelReview::factory()->forBooking($approved)->approved()->create();

        $this->actingAs($customer);
        $bookings = collect($this->page(route('account.hotel-bookings.index'))->json('props.bookings.data'))->keyBy('id');

        $this->assertTrue($bookings[$eligible->id]['review']['can_review']);
        $this->assertFalse($bookings[$ineligible->id]['review']['can_review']);
        $this->assertFalse($bookings[$pending->id]['review']['can_review']);
        $this->assertTrue($bookings[$pending->id]['review']['has_review']);
        $this->assertSame('pending', $bookings[$pending->id]['review']['status']);
        $this->assertSame('approved', $bookings[$approved->id]['review']['status']);
        $this->page(route('account.hotel-bookings.show', $eligible))->assertJsonPath('props.reviewEligibility.can_review', true);
    }

    public function test_customer_review_page_offers_pending_edit_and_published_read_only_view(): void
    {
        $customer = User::factory()->create();
        $booking = $this->completed(customer: $customer);
        $this->actingAs($customer);
        $this->page(route('account.hotel-reviews.show', $booking))->assertJsonPath('component', 'Account/HotelBookings/Review')
            ->assertJsonPath('props.eligibility.can_review', true)->assertJsonPath('props.review', null);
        $review = HotelReview::factory()->forBooking($booking)->pending()->create();
        $this->page(route('account.hotel-reviews.show', $booking))->assertJsonPath('props.eligibility.can_review', false)
            ->assertJsonPath('props.eligibility.can_edit', true)->assertJsonPath('props.review.status', 'pending');
        app(HotelReviewService::class)->moderate($review, $this->admin(), ['action' => 'approve']);

        $this->page(route('account.hotel-reviews.show', $booking))->assertJsonPath('props.eligibility.can_edit', false)
            ->assertJsonPath('props.review.status', 'approved');
    }

    public function test_ineligible_or_disabled_customer_review_ui_does_not_offer_submission(): void
    {
        $booking = $this->completed(overrides: ['status' => HotelBookingStatus::CheckedIn]);
        $this->actingAs($booking->user)->get(route('account.hotel-reviews.show', $booking))->assertRedirect(route('account.hotel-bookings.show', $booking));
        Setting::setValue('hotel.reviews.enabled', '0');

        $this->page(route('account.hotel-bookings.index'))->assertJsonPath('props.reviewsEnabled', false)
            ->assertJsonPath('props.bookings.data.0.review.can_review', false);
        $this->get(route('account.hotel-reviews.show', $booking))->assertNotFound();
    }

    #[DataProvider('adminFilters')]
    public function test_admin_filters_return_only_matching_reviews(string $filter): void
    {
        $vendor = $this->vendor();
        $property = $this->property($vendor);
        $target = $this->review($property, 'pending', ['overall_rating' => 2, 'title' => 'Quiet courtyard', 'comment' => 'Peaceful gardens and friendly reception.', 'created_at' => '2026-09-10 12:00:00']);
        $target->user->update(['name' => 'Unique Guest']);
        $this->review($this->property($this->vendor()), 'approved', ['overall_rating' => 5, 'created_at' => '2026-09-01 12:00:00']);
        $filters = match ($filter) {
            'status' => ['status' => 'pending'],
            'property' => ['property_id' => $property->id],
            'vendor' => ['vendor_profile_id' => $vendor->vendorProfile->id],
            'rating' => ['rating' => 2],
            'date' => ['from' => '2026-09-09', 'to' => '2026-09-11'],
            'title' => ['search' => 'courtyard'],
            'comment' => ['search' => 'Peaceful gardens'],
            'customer' => ['search' => 'Unique Guest'],
        };

        $this->actingAs($this->admin());
        $this->page(route('admin.hotel.reviews.index', $filters))->assertJsonCount(1, 'props.reviews.data')->assertJsonPath('props.reviews.data.0.id', $target->id);
    }

    public static function adminFilters(): array
    {
        return array_map(fn (string $filter): array => [$filter], ['status', 'property', 'vendor', 'rating', 'date', 'title', 'comment', 'customer']);
    }

    public function test_admin_and_vendor_lists_are_bounded_and_vendor_owned(): void
    {
        $vendor = $this->vendor();
        $property = $this->property($vendor);

        foreach (range(1, 21) as $index) {
            $this->review($property, 'pending');
        }

        $foreign = $this->review($this->property($this->vendor()));
        $this->actingAs($vendor);
        $vendorPage = $this->page(route('vendor.hotel.reviews.index', ['per_page' => 500]));
        $vendorPage->assertJsonCount(20, 'props.reviews.data')->assertJsonPath('props.reviews.total', 21);
        $this->assertNotContains($foreign->id, array_column($vendorPage->json('props.reviews.data'), 'id'));
        $this->actingAs($this->admin());
        $this->page(route('admin.hotel.reviews.index', ['per_page' => 500]))->assertJsonCount(20, 'props.reviews.data')->assertJsonPath('props.reviews.total', 22);
    }

    public function test_new_pending_reviews_alert_only_authorized_moderators(): void
    {
        $moderator = $this->staff(['hotel.reviews.view', 'hotel.reviews.moderate']);
        $viewer = $this->staff(['hotel.reviews.view']);
        $vendor = $this->vendor();
        $booking = $this->completed($this->property($vendor));

        $this->actingAs($booking->user)->post(route('account.hotel-reviews.store', $booking), $this->payload())->assertRedirect();

        $this->assertSame(['hotel_review_pending'], $moderator->notifications->pluck('data.kind')->all());
        $this->assertSame(0, $viewer->notifications()->count());
        $this->assertSame(0, $vendor->notifications()->count());
        $this->assertSame(0, $booking->user->notifications()->count());
    }

    public function test_review_reads_and_filters_do_not_write_audits_or_notifications(): void
    {
        $vendor = $this->vendor();
        $review = $this->review($this->property($vendor));
        $admin = $this->admin();
        $auditCount = ActivityLog::count();
        $notificationCount = DB::table('notifications')->count();

        $this->page(route('hotels.show', $review->property));
        $this->actingAs($admin)->get(route('admin.hotel.reviews.index', ['rating' => $review->overall_rating]))->assertOk();
        $this->get(route('admin.hotel.reviews.show', $review))->assertOk();
        $this->actingAs($vendor)->get(route('vendor.hotel.reviews.show', $review))->assertOk();

        $this->assertSame($auditCount, ActivityLog::count());
        $this->assertSame($notificationCount, DB::table('notifications')->count());
    }

    public function test_review_settings_are_validated_and_permission_protected(): void
    {
        $payload = [];

        foreach (HotelSettings::all() as $key => $value) {
            $payload[str_replace('.', '_', $key)] = $value;
        }

        $payload['hotel_reviews_minimum_comment_length'] = 35;
        $payload['hotel_reviews_moderation_enabled'] = '0';
        $this->actingAs($this->staff(['hotel.reviews.view']))->postJson(route('admin.hotel.settings.update'), $payload)->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.hotel.settings.update'), $payload)->assertRedirect();
        $this->assertSame('35', Setting::getValue('hotel.reviews.minimum_comment_length'));
        $this->assertSame('0', Setting::getValue('hotel.reviews.moderation_enabled'));
        $this->postJson(route('admin.hotel.settings.update'), array_merge($payload, ['hotel_reviews_minimum_comment_length' => 1]))->assertJsonValidationErrors('hotel_reviews_minimum_comment_length');
        $this->assertSame('35', Setting::getValue('hotel.reviews.minimum_comment_length'));
    }

    public function test_review_submission_is_rate_limited(): void
    {
        $booking = $this->completed();
        $this->actingAs($booking->user)->post(route('account.hotel-reviews.store', $booking), $this->payload())->assertRedirect();

        foreach (range(1, 9) as $attempt) {
            $this->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertUnprocessable();
        }

        $this->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertTooManyRequests();
        $this->assertDatabaseCount('hotel_reviews', 1);
    }

    public function test_existing_administrator_role_receives_review_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('administrator');
        $review = $this->review(status: 'pending');

        $this->actingAs($admin)->get(route('admin.hotel.reviews.index'))->assertOk();
        $this->patch(route('admin.hotel.reviews.moderate', $review), ['action' => 'approve'])->assertRedirect();
        $this->post(route('admin.hotel.reviews.reply', $review), ['reply' => 'Thank you for sharing your experience.'])->assertRedirect();
        $this->assertSame('approved', $review->fresh()->status);
        $this->assertNotNull($review->fresh()->vendor_reply);
    }

    public function test_completed_stay_review_workflow_and_module_toggle_smoke(): void
    {
        $vendor = $this->vendor();
        $admin = $this->admin();
        $customer = User::factory()->create();
        $property = $this->property($vendor);
        $room = HotelRoomType::factory()->create(['property_id' => $property->id, 'status' => RoomTypeStatus::Active, 'total_units' => 2]);
        $plan = HotelRatePlan::factory()->create(['property_id' => $property->id, 'hotel_room_type_id' => $room->id, 'currency' => 'USD', 'base_rate' => '100.00']);
        $booking = app(HotelBookingService::class)->create([
            'room_type_id' => $room->id, 'rate_plan_id' => $plan->id,
            'check_in' => '2027-04-10', 'check_out' => '2027-04-12', 'rooms' => 1, 'adults' => 2, 'children' => 0,
            'guest_name' => 'Smoke Guest', 'guest_email' => 'smoke@example.test', 'guest_phone' => '1234567890',
        ], $customer, $customer);
        $snapshot = $booking->pricing_snapshot;

        $this->actingAs($admin);
        foreach (['checked_in', 'checked_out', 'completed'] as $status) {
            $this->patch(route('admin.hotel.operations.status', $booking), ['status' => $status])->assertRedirect();
        }

        $this->actingAs($customer);
        $this->page(route('account.hotel-bookings.index'))->assertJsonPath('props.bookings.data.0.review.can_review', true);
        $this->page(route('account.hotel-reviews.show', $booking))->assertJsonPath('props.eligibility.can_review', true);
        $this->post(route('account.hotel-reviews.store', $booking), $this->payload())->assertRedirect();
        $review = HotelReview::sole();
        $this->page(route('account.hotel-reviews.show', $booking))->assertJsonPath('props.review.status', 'pending');
        $this->postJson(route('account.hotel-reviews.store', $booking), $this->payload())->assertJsonValidationErrors('booking');
        $this->actingAs($admin);
        $this->page(route('admin.hotel.reviews.index', ['status' => 'pending']))->assertJsonPath('props.reviews.data.0.id', $review->id);
        $this->patch(route('admin.hotel.reviews.moderate', $review), ['action' => 'approve'])->assertRedirect();
        $this->actingAs($vendor);
        $this->page(route('vendor.hotel.reviews.show', $review))->assertJsonPath('props.canReply', true);
        $this->put(route('vendor.hotel.reviews.reply', $review), ['reply' => 'Thank you for choosing our property for your stay.'])->assertRedirect();
        $this->page(route('hotels.show', $property))->assertJsonPath('props.reviews.data.0.verified_stay', true)
            ->assertJsonPath('props.seo.structuredData.aggregateRating.reviewCount', 1);

        $this->actingAs($admin)->patch(route('admin.modules.update', 'hotel'), ['enabled' => false])->assertRedirect();
        $this->get(route('admin.hotel.reviews.index'))->assertNotFound();
        $this->page(route('admin.modules.index'))->assertJsonPath('component', 'Admin/Modules/Index');
        $this->patch(route('admin.modules.update', 'hotels'), ['enabled' => true])->assertRedirect();
        $this->page(route('hotels.show', $property))->assertJsonPath('props.reviewSummary.reviews_count', 1);
        $this->assertSame($snapshot, $booking->fresh()->pricing_snapshot);
        $this->assertSame(2, $booking->reservationNights()->count());
        $this->assertSame(HotelBookingStatus::Completed, $booking->fresh()->status);
    }

    private function page(string $url): TestResponse
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create($url));

        return $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->assertOk();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('super-admin');

        return $user->fresh();
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $role = Role::create(['name' => 'review-staff-'.$user->id, 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function vendor(): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return $user->fresh();
    }

    private function property(?User $vendor = null): Property
    {
        return Property::factory()->create(['vendor_profile_id' => $vendor?->vendorProfile?->id, 'status' => PropertyStatus::Published->value, 'published_at' => now()]);
    }

    private function completed(?Property $property = null, ?User $customer = null, array $overrides = []): HotelBooking
    {
        $property ??= $this->property();
        $customer ??= User::factory()->create();

        return HotelBooking::factory()->create(array_merge([
            'property_id' => $property->id, 'vendor_profile_id' => $property->vendor_profile_id,
            'property_name_snapshot' => $property->name, 'user_id' => $customer->id,
            'status' => HotelBookingStatus::Completed, 'check_in' => '2026-09-10', 'check_out' => '2026-09-12',
            'completed_at' => '2026-09-12 11:00:00',
        ], $overrides));
    }

    private function review(?Property $property = null, string $status = 'approved', array $overrides = []): HotelReview
    {
        return HotelReview::factory()->forBooking($this->completed($property))->{$status}()->create($overrides);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'overall_rating' => 4, 'cleanliness_rating' => 5, 'location_rating' => 4,
            'service_rating' => 5, 'comfort_rating' => 4, 'value_rating' => 4,
            'title' => 'A pleasant stay', 'comment' => 'The room was comfortable and the staff were welcoming throughout our stay.',
        ], $overrides);
    }
}
