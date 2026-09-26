<?php

namespace App\AI\DTOs;

final readonly class AIToolRequest
{
    /** @param list<AIMessage> $messages
     * @param  list<array{name:string,description:string,input_schema:array<string,mixed>}>  $tools
     */
    public function __construct(public array $messages, public array $tools, public bool $requireTool = false, public ?string $model = null) {}

    public function withModel(string $model): self
    {
        return new self($this->messages, $this->tools, $this->requireTool, $model);
    }
}
