<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_guest_can_submit_a_review_that_remains_pending(): void
    {
        $package = TourPackage::factory()->create();

        $this->post(route('packages.reviews.store', $package, absolute: false), [
            'name' => 'Radha Sharma',
            'email' => 'radha@example.com',
            'rating' => 5,
            'comment' => 'A beautifully organized and peaceful tour.',
        ])->assertRedirect();

        $review = Review::firstOrFail();
        $this->assertNull($review->user_id);
        $this->assertSame('Radha Sharma', $review->reviewer_name);
        $this->assertSame('radha@example.com', $review->reviewer_email);
        $this->assertSame(5, $review->rating);
        $this->assertFalse($review->is_approved);
    }

    public function test_review_submission_requires_valid_rating_and_comment(): void
    {
        $package = TourPackage::factory()->create();

        $this->post(route('packages.reviews.store', $package, absolute: false), [
            'name' => '',
            'rating' => 6,
            'comment' => 'Short',
        ])->assertSessionHasErrors(['name', 'rating', 'comment']);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_only_approved_reviews_are_displayed_and_counted(): void
    {
        $package = TourPackage::factory()->create();
        Review::create([
            'package_id' => $package->id,
            'reviewer_name' => 'Approved Guest',
            'reviewer_email' => 'private@example.com',
            'rating' => 4,
            'comment' => 'A lovely visit with a helpful local guide.',
            'is_approved' => true,
        ]);
        Review::create([
            'package_id' => $package->id,
            'reviewer_name' => 'Pending Guest',
            'rating' => 1,
            'comment' => 'This pending review must remain private.',
            'is_approved' => false,
        ]);

        $this->get(route('packages.show', $package, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Show')
                ->where('package.approved_reviews_count', 1)
                ->where('package.approved_reviews_avg_rating', 4)
                ->has('reviews.data', 1)
                ->where('reviews.data.0.reviewer_name', 'Approved Guest')
                ->missing('reviews.data.0.reviewer_email'));
    }

    public function test_rapid_repeat_submissions_are_throttled(): void
    {
        $package = TourPackage::factory()->create();
        $payload = [
            'name' => 'Guest Reviewer',
            'rating' => 5,
            'comment' => 'A very memorable tour around Braj Bhoomi.',
        ];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('packages.reviews.store', $package, absolute: false), $payload)->assertRedirect();
        }

        $this->post(route('packages.reviews.store', $package, absolute: false), $payload)->assertTooManyRequests();
        $this->assertDatabaseCount('reviews', 3);
    }
}
