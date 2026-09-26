<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AIExecutionContext;
use App\AI\Support\AIToolAction;

interface AIToolInterface
{
    public function name(): string;

    public function description(): string;

    /** @return array<string, mixed> */
    public function inputSchema(): array;

    public function action(): AIToolAction;

    public function requiredPermission(): string;

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(AIExecutionContext $context, array $arguments): array;
}
