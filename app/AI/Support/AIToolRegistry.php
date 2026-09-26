<?php

namespace App\AI\Support;

use App\AI\Contracts\AIActionToolInterface;
use App\AI\Contracts\AIToolInterface;
use App\AI\DTOs\AIExecutionContext;

class AIToolRegistry
{
    /** @var array<string, AIToolInterface> */
    private array $tools = [];

    /** @var array<string, AIActionToolInterface> */
    private array $proposalTools = [];

    public function register(AIToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function registerAction(AIActionToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
        $this->proposalTools[$tool->name()] = $tool;
    }

    public function allowed(string $name, AIExecutionContext $context, AIToolGuardrail $guardrail): ?AIToolInterface
    {
        $tool = $this->tools[$name] ?? null;

        return $tool && $guardrail->allows($tool, $context) ? $tool : null;
    }

    public function proposable(string $name, AIExecutionContext $context, AIToolGuardrail $guardrail): ?AIActionToolInterface
    {
        $tool = $this->proposalTools[$name] ?? null;

        return $tool instanceof AIActionToolInterface && $guardrail->allowsProposal($tool, $context) ? $tool : null;
    }

    /** @return list<array{name: string, description: string, action: string, input_schema: array<string, mixed>}> */
    public function metadata(): array
    {
        return array_values(array_map(fn (AIToolInterface $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'action' => $tool->action()->value,
            'input_schema' => $tool->inputSchema(),
        ], $this->tools));
    }
}
