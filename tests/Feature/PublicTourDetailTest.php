<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\Place;
use App\Models\Review;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicTourDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_public_detail_exposes_a_safe_ordered_tour_contract(): void
    {
        $package = TourPackage::factory()->create([
            'title' => 'Coastal Story Tour',
            'price' => 5000,
            'duration_days' => 2,
            'duration_nights' => 1,
            'day_wise_itinerary' => [
                ['day' => 1, 'title' => 'First light', 'details' => ['An early start']],
                ['day' => 2, 'title' => 'The return', 'details' => ['A final walk']],
            ],
            'cover_image' => null,
            'gallery' => [],
        ]);
        $destination = Destination::factory()->create(['is_active' => true]);
        $place = Place::factory()->create(['destination_id' => $destination->id, 'is_active' => true]);
        $package->destinations()->attach($destination);
        $package->places()->attach($place);
        Review::create([
            'package_id' => $package->id,
            'reviewer_name' => 'Published guest',
            'rating' => 5,
            'comment' => 'A considered and memorable journey.',
            'is_approved' => true,
        ]);
        Review::create([
            'package_id' => $package->id,
            'reviewer_name' => 'Pending guest',
            'rating' => 1,
            'comment' => 'This review is not public.',
            'is_approved' => false,
        ]);

        $this->get(route('packages.show', $package, absolute: false).'?travel_date='.Carbon::today()->addWeek()->toDateString().'&adults=3&children=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Packages/Show')
                ->where('context.adults', 3)
                ->where('context.children', 2)
                ->where('package.title', 'Coastal Story Tour')
                ->where('package.duration_days', 2)
                ->where('package.duration_nights', 1)
                ->where('package.approved_reviews_count', 1)
                ->where('package.approved_reviews_avg_rating', 5)
                ->where('package.itinerary.0.title', 'First light')
                ->where('package.itinerary.1.title', 'The return')
                ->has('package.places', 1)
                ->has('reviews.data', 1)
                ->missing('package.vendor_profile_id')
                ->missing('package.created_by')
                ->missing('package.moderation_status')
                ->missing('reviews.data.0.reviewer_email'));
    }

    public function test_unpublished_tour_detail_is_hidden(): void
    {
        $package = TourPackage::factory()->inactive()->create();

        $this->get(route('packages.show', $package, absolute: false))->assertNotFound();
    }

    public function test_booking_handoff_preserves_valid_selection_context(): void
    {
        $package = TourPackage::factory()->create();

        $this->get(route('booking.form', $package, absolute: false).'?travel_date=2030-05-06&adults=4&children=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Create')
                ->where('selection.travel_date', '2030-05-06')
                ->where('selection.adults', 4)
                ->where('selection.children', 2));
    }

    public function test_invalid_detail_date_becomes_a_safe_no_date_state(): void
    {
        $package = TourPackage::factory()->create();

        $this->get(route('packages.show', $package, absolute: false).'?travel_date=not-a-date&adults=-3&children=-2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('context.travel_date', null)
                ->where('context.invalid_date', true)
                ->where('context.adults', 1)
                ->where('context.children', 0)
                ->where('bookability.bookable', null));
    }
}
