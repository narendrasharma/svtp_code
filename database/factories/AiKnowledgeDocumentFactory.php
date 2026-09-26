<?php

namespace Database\Factories;

use App\Models\AiKnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKnowledgeDocument>
 */
class AiKnowledgeDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'source_type' => 'manual',
            'content' => fake()->paragraphs(3, true),
            'status' => 'inactive',
            'visibility' => 'internal',
        ];
    }
}
