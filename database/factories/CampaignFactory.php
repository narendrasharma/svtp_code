<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'reference' => 'CMP-'.fake()->unique()->numerify('######'),
            'name' => fake()->sentence(3),
            'subject' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'channel' => 'email',
            'status' => 'draft',
            'audience_type' => Campaign::AUDIENCE_ALL_CUSTOMERS,
            'created_by' => User::factory()->state(['role' => 'admin']),
        ];
    }
}
