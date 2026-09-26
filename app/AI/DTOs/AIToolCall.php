<?php

namespace App\AI\DTOs;

final readonly class AIToolCall
{
    /** @param array<string, mixed> $arguments */
    public function __construct(public string $id, public string $name, public array $arguments) {}
}
