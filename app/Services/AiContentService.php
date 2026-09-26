<?php

namespace App\Services;

use App\AI\DTOs\AIRequest;
use App\AI\Support\AIManager;

class AiContentService
{
    public function __construct(private readonly AIManager $ai) {}

    public function generateItineraryDraft(string $destinationNotes): string
    {
        $prompt = 'Write an engaging, warm day-wise pilgrimage itinerary description '
            ."for Indian devotees based on these stops: {$destinationNotes}. "
            .'Keep it welcoming, factual, and under 150 words.';

        return $this->ai->generate(new AIRequest($prompt), 'itinerary_draft')->text;
    }
}
