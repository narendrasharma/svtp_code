<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\AiToolEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiToolEvent>
 */
class AiToolEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'tool_name' => 'search_tours',
            'succeeded' => true,
            'duration_ms' => 5,
            'result_count' => 1,
            'created_at' => now(),
        ];
    }
}
