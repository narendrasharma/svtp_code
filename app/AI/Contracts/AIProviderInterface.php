<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AIRequest;
use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ProviderCapabilities;

interface AIProviderInterface
{
    public function key(): string;

    public function capabilities(): ProviderCapabilities;

    public function generate(AIRequest $request): AIResponse;
}
