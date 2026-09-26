<?php

namespace Database\Factories;

use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiActionProposal>
 */
class AiActionProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'ai_conversation_id' => AiConversation::factory(),
            'tool_name' => 'set_tour_featured',
            'target_type' => 'tour',
            'target_id' => 1,
            'target_label' => 'Example Tour',
            'field_label' => 'Featured',
            'summary' => 'Set Tour featured state.',
            'risk_level' => 'low',
            'status' => 'pending',
            'validated_arguments' => ['tour_id' => 1, 'featured' => true],
            'before_snapshot' => ['is_featured' => false],
            'proposed_changes' => ['is_featured' => true],
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
