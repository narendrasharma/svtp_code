<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'reference' => 'LEAD-'.fake()->unique()->numerify('######'),
            'name' => fake()->name(),
            'phone' => fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'service_type' => 'tour',
            'priority' => 'normal',
            'status' => 'new',
            'created_by' => User::factory(),
        ];
    }
}
