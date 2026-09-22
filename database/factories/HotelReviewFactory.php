<?php

namespace Database\Factories;

use App\Enums\HotelBookingStatus;
use App\Models\HotelBooking;
use App\Models\HotelReview;
use App\Models\User;
use App\Services\HotelRatingSummaryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelReview>
 */
class HotelReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_booking_id' => HotelBooking::factory()->state([
                'status' => HotelBookingStatus::Completed,
                'check_in' => now()->subDays(5)->toDateString(),
                'check_out' => now()->subDays(3)->toDateString(),
                'completed_at' => now()->subDays(3),
            ]),
            'property_id' => fn (array $attributes) => HotelBooking::findOrFail($attributes['hotel_booking_id'])->property_id,
            'user_id' => fn (array $attributes) => HotelBooking::findOrFail($attributes['hotel_booking_id'])->user_id,
            'overall_rating' => fake()->numberBetween(1, 5),
            'cleanliness_rating' => fake()->numberBetween(1, 5),
            'location_rating' => fake()->numberBetween(1, 5),
            'service_rating' => fake()->numberBetween(1, 5),
            'comfort_rating' => fake()->numberBetween(1, 5),
            'value_rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(4),
            'comment' => 'The room was comfortable and the staff helped us throughout our stay.',
            'status' => HotelReview::STATUS_PENDING,
            'verified_stay' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (HotelReview $review): void {
            $booking = HotelBooking::findOrFail($review->hotel_booking_id);

            if ($booking->status !== HotelBookingStatus::Completed || $booking->user_id === null || $booking->property_id === null) {
                throw new \LogicException('Hotel review factories require an owned, completed booking with a property.');
            }

            $review->forceFill(['property_id' => $booking->property_id, 'user_id' => $booking->user_id, 'verified_stay' => true]);
        })->afterCreating(function (HotelReview $review): void {
            if ($review->status === HotelReview::STATUS_APPROVED) {
                app(HotelRatingSummaryService::class)->refresh($review->property_id);
            }
        });
    }

    public function forBooking(HotelBooking $booking): static
    {
        return $this->state(['hotel_booking_id' => $booking->id, 'property_id' => $booking->property_id, 'user_id' => $booking->user_id]);
    }

    public function pending(): static
    {
        return $this->state(['status' => HotelReview::STATUS_PENDING, 'published_at' => null]);
    }

    public function approved(): static
    {
        return $this->state(['status' => HotelReview::STATUS_APPROVED, 'published_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => HotelReview::STATUS_REJECTED, 'published_at' => null, 'moderated_at' => now(), 'rejection_reason' => 'Not suitable for publication.']);
    }

    public function fiveStar(): static
    {
        return $this->state(array_fill_keys(['overall_rating', ...array_keys(HotelReview::CATEGORY_RATINGS)], 5));
    }

    public function lowRating(): static
    {
        return $this->state(array_fill_keys(['overall_rating', ...array_keys(HotelReview::CATEGORY_RATINGS)], 1));
    }

    public function withVendorReply(): static
    {
        return $this->approved()->afterMaking(function (HotelReview $review): void {
            $review->forceFill([
                'vendor_reply' => 'Thank you for sharing your stay with us. We appreciate your feedback.',
                'replied_by' => $review->property->vendorProfile?->user_id ?? User::factory()->create(['role' => 'admin'])->id,
                'replied_at' => now(),
            ]);
        });
    }
}
