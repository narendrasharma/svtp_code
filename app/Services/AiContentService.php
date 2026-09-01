<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Provider-agnostic AI content helper for the admin panel.
 * Switch AI_PROVIDER in .env between openai | gemini | claude without
 * touching the admin controller/UI.
 */
class AiContentService
{
    public function generateItineraryDraft(string $destinationNotes): string
    {
        $prompt = "Write an engaging, warm day-wise pilgrimage itinerary description "
            . "for Indian devotees based on these stops: {$destinationNotes}. "
            . "Keep it welcoming, factual, and under 150 words.";

        return match (config('services.ai.provider', 'openai')) {
            'gemini' => $this->callGemini($prompt),
            'claude' => $this->callClaude($prompt),
            default => $this->callOpenAi($prompt),
        };
    }

    protected function callOpenAi(string $prompt): string
    {
        $response = Http::withToken(config('services.ai.openai_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        return $response->json('choices.0.message.content', '');
    }

    protected function callGemini(string $prompt): string
    {
        $key = config('services.ai.gemini_key');
        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$key}",
            ['contents' => [['parts' => [['text' => $prompt]]]]]
        );

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    protected function callClaude(string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.ai.anthropic_key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 400,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return $response->json('content.0.text', '');
    }
}
