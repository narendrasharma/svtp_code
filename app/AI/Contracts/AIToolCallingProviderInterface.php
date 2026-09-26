<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AIToolRequest;
use App\AI\DTOs\AIToolResponse;

interface AIToolCallingProviderInterface extends AIProviderInterface
{
    public function chatWithTools(AIToolRequest $request): AIToolResponse;
}
