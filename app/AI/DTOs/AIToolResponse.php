<?php

namespace App\AI\DTOs;

final readonly class AIToolResponse
{
    /** @param list<AIToolCall> $toolCalls
     * @param  array<string, int>|null  $usage
     */
    public function __construct(
        public string $text,
        public array $toolCalls,
        public string $provider,
        public string $model,
        public ?array $usage = null,
        public ?string $providerState = null,
    ) {}
}
