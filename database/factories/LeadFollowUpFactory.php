<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadFollowUp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadFollowUp>
 */
class LeadFollowUpFactory extends Factory
{
    protected $model = LeadFollowUp::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'due_at' => now()->addDay(),
            'type' => 'call',
            'status' => 'pending',
            'note' => fake()->sentence(),
        ];
    }
}
