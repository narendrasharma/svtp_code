<?php

namespace Database\Factories;

use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enquiry_type' => 'tour_plan',
            'status' => 'new',
            'tour_package_id' => null,
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'pickup_drop' => 'Delhi to Vrindavan',
            'hotel_category' => 'standard',
            'adults' => 2,
            'children' => 0,
            'arrival_date' => now()->addWeek()->toDateString(),
            'departure_date' => now()->addWeek()->addDays(2)->toDateString(),
            'message' => fake()->sentence(),
        ];
    }
}
