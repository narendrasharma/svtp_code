<?php

namespace App\AI\DTOs;

final readonly class AIResponse
{
    /** @param array<string, int>|null $usage */
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public ?array $usage = null,
        public ?string $finishStatus = null,
        public ?string $requestId = null,
    ) {}
}
