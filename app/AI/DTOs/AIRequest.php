<?php

namespace App\AI\DTOs;

final readonly class AIRequest
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $userContent,
        public ?string $systemInstructions = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?string $model = null,
        public array $metadata = [],
    ) {}

    public function withModel(string $model): self
    {
        return new self($this->userContent, $this->systemInstructions, $this->temperature, $this->maxTokens, $model, $this->metadata);
    }
}
