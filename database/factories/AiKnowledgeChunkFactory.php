<?php

namespace Database\Factories;

use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKnowledgeChunk>
 */
class AiKnowledgeChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_knowledge_document_id' => AiKnowledgeDocument::factory(),
            'chunk_index' => 0,
            'content' => fake()->paragraph(),
            'embedding' => [0.1, 0.2, 0.3],
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'content_hash' => hash('sha256', fake()->sentence()),
        ];
    }
}
