<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TourPackage>
 */
class TourPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(4, true)).' Tour';
        $days = fake()->numberBetween(1, 6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'city_id' => City::factory(),
            'duration_days' => $days,
            'duration_nights' => max(0, $days - 1),
            'price' => fake()->numberBetween(2000, 25000),
            'overview' => fake()->paragraph(),
            'inclusions' => ['Private AC vehicle', 'Local assistance'],
            'exclusions' => ['Meals', 'Monument entry fees'],
            'category' => TourCategory::factory(),
            'is_featured' => false,
            'is_active' => true,
            'moderation_status' => 'approved',
            'vendor_profile_id' => null,
            'created_by' => null,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['moderation_status' => 'draft', 'is_active' => false]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes): array => ['moderation_status' => 'pending_review', 'is_active' => false]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => ['moderation_status' => 'approved', 'is_active' => true]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => ['moderation_status' => 'rejected', 'is_active' => false]);
    }

    public function changesRequested(): static
    {
        return $this->state(fn (array $attributes): array => ['moderation_status' => 'changes_requested', 'is_active' => false]);
    }

    public function forVendor(VendorProfile $profile): static
    {
        return $this->state(fn (array $attributes): array => [
            'vendor_profile_id' => $profile->id,
            'created_by' => $profile->user_id,
        ]);
    }
}
