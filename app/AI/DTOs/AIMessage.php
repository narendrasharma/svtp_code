<?php

namespace App\AI\DTOs;

final readonly class AIMessage
{
    /** @param list<AIToolCall> $toolCalls */
    public function __construct(
        public string $role,
        public string $content,
        public array $toolCalls = [],
        public ?string $toolCallId = null,
        public ?string $providerState = null,
    ) {}
}
