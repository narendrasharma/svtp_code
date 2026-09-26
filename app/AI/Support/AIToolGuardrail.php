<?php

namespace App\AI\Support;

use App\AI\Contracts\AIActionToolInterface;
use App\AI\Contracts\AIToolInterface;
use App\AI\DTOs\AIExecutionContext;

class AIToolGuardrail
{
    public function allows(AIToolInterface $tool, AIExecutionContext $context): bool
    {
        return $context->userId !== null
            && $tool->action() !== AIToolAction::Write
            && in_array($tool->requiredPermission(), $context->permissions, true);
    }

    public function allowsProposal(AIActionToolInterface $tool, AIExecutionContext $context): bool
    {
        return $context->userId !== null && $context->role === 'admin'
            && $tool->action() === AIToolAction::Write
            && in_array('ai.assistant.use', $context->permissions, true)
            && in_array($tool->requiredPermission(), $context->permissions, true)
            && count(array_diff($tool->additionalPermissions(), $context->permissions)) === 0;
    }
}
