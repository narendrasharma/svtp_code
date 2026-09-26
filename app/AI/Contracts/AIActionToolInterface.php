<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AIExecutionContext;
use App\Models\AiActionProposal;

interface AIActionToolInterface extends AIToolInterface
{
    public function riskLevel(): string;

    /** @return list<string> */
    public function additionalPermissions(): array;

    /** @param array<string,mixed> $arguments
     * @return array{target_type:string,target_id:int,target_label:string,field_label:string,summary:string,validated_arguments:array<string,mixed>,before_snapshot:array<string,mixed>,proposed_changes:array<string,mixed>}
     */
    public function preview(AIExecutionContext $context, array $arguments): array;

    /** Must run inside a transaction and lock the target before comparing its current field value.
     * @return array<string,mixed>
     */
    public function executeConfirmed(AIExecutionContext $context, AiActionProposal $proposal): array;
}
