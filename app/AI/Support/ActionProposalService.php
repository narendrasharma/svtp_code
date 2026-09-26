<?php

namespace App\AI\Support;

use App\AI\Contracts\AIActionToolInterface;
use App\AI\DTOs\AIExecutionContext;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ModuleManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ActionProposalService
{
    public function __construct(
        private readonly AISettings $settings,
        private readonly AIToolRegistry $registry,
        private readonly AIToolGuardrail $guardrail,
        private readonly ModuleManager $modules,
        private readonly ActivityLogger $audit,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->enabled() && $this->settings->agentActionsEnabled();
    }

    public function availableForQuestion(AIActionToolInterface $tool, AIExecutionContext $context, string $question): bool
    {
        return $this->enabled() && ! preg_match('/\b(all|every|bulk|batch)\b/i', $question)
            && $this->registry->proposable($tool->name(), $context, $this->guardrail) === $tool
            && $tool->available($this->modules) && $this->hasExplicitIntent($question, $tool->name());
    }

    /** @param array<string,mixed> $arguments
     * @return array{tool_name:string,preview:array<string,mixed>}
     */
    public function prepare(AIExecutionContext $context, string $question, string $toolName, array $arguments): array
    {
        if (! $this->enabled()) {
            throw ActionException::disabled();
        }

        $tool = $this->registry->proposable($toolName, $context, $this->guardrail);

        if (! $tool || ! $this->availableForQuestion($tool, $context, $question)) {
            throw ActionException::denied();
        }

        return ['tool_name' => $toolName, 'preview' => $tool->preview($context, $arguments)];
    }

    /** @param array{tool_name:string,preview:array<string,mixed>} $prepared */
    public function persist(User $user, AiConversation $conversation, array $prepared): AiActionProposal
    {
        $context = $this->context($user);

        if (! $this->enabled()) {
            throw ActionException::disabled();
        }

        $tool = $this->registry->proposable($prepared['tool_name'], $context, $this->guardrail);

        if (! $tool || ! $tool->available($this->modules) || $conversation->user_id !== $user->id) {
            throw ActionException::denied();
        }

        $preview = $tool->preview($context, $prepared['preview']['validated_arguments']);

        if ($preview['before_snapshot'] !== $prepared['preview']['before_snapshot']
            || $preview['proposed_changes'] !== $prepared['preview']['proposed_changes']) {
            throw ActionException::stale();
        }

        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $existing = AiActionProposal::query()
            ->where('user_id', $user->id)
            ->where('ai_conversation_id', $conversation->id)
            ->where('tool_name', $tool->name())
            ->where('target_type', $preview['target_type'])
            ->where('target_id', $preview['target_id'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (AiActionProposal $candidate): bool => $candidate->validated_arguments === $preview['validated_arguments']
                && $candidate->before_snapshot === $preview['before_snapshot']
                && $candidate->proposed_changes === $preview['proposed_changes']);

        if ($existing) {
            return $existing;
        }

        $proposal = AiActionProposal::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
            'tool_name' => $tool->name(),
            'target_type' => $preview['target_type'], 'target_id' => $preview['target_id'],
            'target_label' => $preview['target_label'], 'field_label' => $preview['field_label'],
            'summary' => $preview['summary'], 'risk_level' => $tool->riskLevel(),
            'status' => 'pending', 'validated_arguments' => $preview['validated_arguments'],
            'before_snapshot' => $preview['before_snapshot'], 'proposed_changes' => $preview['proposed_changes'],
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->audit->log('ai.action.proposed', 'ai', $proposal->summary, null, null, [
            'proposal_id' => $proposal->id, 'tool_name' => $proposal->tool_name, 'target_type' => $proposal->target_type,
            'target_id' => $proposal->target_id, 'status' => 'pending',
        ], $user);

        Log::info('AI action proposed', ['tool' => $proposal->tool_name, 'risk' => $proposal->risk_level]);

        return $proposal;
    }

    /** @return array{proposal:array<string,mixed>,result:array<string,mixed>} */
    public function confirm(User $user, string $proposalId): array
    {
        try {
            return DB::transaction(function () use ($user, $proposalId): array {
                $proposal = AiActionProposal::query()->whereKey($proposalId)->where('user_id', $user->id)
                    ->lockForUpdate()->firstOrFail();

                if ($proposal->status === 'executed') {
                    return ['proposal' => $this->present($proposal), 'result' => $proposal->result ?? []];
                }

                if ($proposal->status !== 'pending') {
                    throw ActionException::invalid();
                }

                if ($proposal->expires_at->isPast()) {
                    throw ActionException::expired();
                }

                if (! $this->enabled()) {
                    throw ActionException::disabled();
                }

                $context = $this->context($user);
                $tool = $this->registry->proposable($proposal->tool_name, $context, $this->guardrail);

                if (! $tool || ! $tool->available($this->modules)) {
                    throw ActionException::denied();
                }

                $proposal->update(['status' => 'confirmed', 'confirmed_at' => now()]);
                $result = $tool->executeConfirmed($context, $proposal);
                $proposal->update([
                    'status' => 'executed', 'executed_at' => now(), 'result' => $result,
                ]);
                $this->audit->log('ai.action.executed', 'ai', $proposal->summary, null,
                    $proposal->before_snapshot, $proposal->proposed_changes + [
                        'proposal_id' => $proposal->id, 'target_type' => $proposal->target_type, 'target_id' => $proposal->target_id,
                    ], $user);
                $proposal->conversation?->messages()->create([
                    'role' => 'assistant', 'content' => 'Confirmed by user. Action completed: '.$proposal->summary,
                ]);
                Log::info('AI action executed', ['tool' => $proposal->tool_name, 'status' => 'executed']);

                return ['proposal' => $this->present($proposal), 'result' => $result];
            });
        } catch (ActionException $exception) {
            if (in_array($exception->reason, ['expired', 'stale', 'missing', 'denied', 'disabled'], true)) {
                $this->finishFailed($user, $proposalId, $exception->reason);
            }

            throw $exception;
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->finishFailed($user, $proposalId, 'execution_failed');
            Log::error('AI action execution failed', ['reason' => 'execution_failed']);

            throw ActionException::unavailable();
        }
    }

    /** @return array<string,mixed> */
    public function reject(User $user, string $proposalId): array
    {
        return DB::transaction(function () use ($user, $proposalId): array {
            $proposal = AiActionProposal::query()->whereKey($proposalId)->where('user_id', $user->id)
                ->lockForUpdate()->firstOrFail();

            if ($proposal->status !== 'pending') {
                throw ActionException::invalid();
            }

            $proposal->update(['status' => 'rejected']);
            $this->audit->log('ai.action.rejected', 'ai', $proposal->summary, null, null,
                ['proposal_id' => $proposal->id, 'status' => 'rejected'], $user);
            $proposal->conversation?->messages()->create(['role' => 'assistant', 'content' => 'Action proposal rejected. No change was made.']);

            return $this->present($proposal);
        });
    }

    /** @return array<string,mixed> */
    public function present(AiActionProposal $proposal): array
    {
        return [
            'id' => $proposal->id, 'tool_name' => $proposal->tool_name,
            'summary' => $proposal->summary, 'target_type' => $proposal->target_type,
            'target_id' => $proposal->target_id, 'target_label' => $proposal->target_label,
            'field_label' => $proposal->field_label, 'before' => array_values($proposal->before_snapshot ?? [])[0] ?? null,
            'proposed' => array_values($proposal->proposed_changes ?? [])[0] ?? null,
            'risk_level' => $proposal->risk_level,
            'status' => $proposal->status === 'pending' && $proposal->expires_at->isPast() ? 'expired' : $proposal->status,
            'expires_at' => $proposal->expires_at->toIso8601String(), 'created_at' => $proposal->created_at->toIso8601String(),
            'executed_at' => $proposal->executed_at?->toIso8601String(), 'result' => $proposal->result,
            'failure_reason' => $proposal->failure_reason,
        ];
    }

    private function context(User $user): AIExecutionContext
    {
        if (! $user->isAdmin() || ! $user->hasStaffPermission('ai.assistant.use')) {
            throw ActionException::denied();
        }

        return new AIExecutionContext($user->id, 'admin', $user->staffPermissionNames(), locale: app()->getLocale());
    }

    private function hasExplicitIntent(string $question, string $toolName): bool
    {
        return match ($toolName) {
            'set_tour_featured' => (bool) preg_match('/\b(feature|unfeature|featured|highlight)\b/i', $question)
                && (bool) preg_match('/\b(make|mark|set|turn|toggle|add|remove|feature|unfeature|highlight)\b/i', $question)
                && ! preg_match('/\b(hotel|property)\b/i', $question),
            'update_destination_excerpt' => (bool) preg_match('/\b(update|change|replace|rewrite|set|edit)\b/i', $question)
                && (bool) preg_match('/\b(destination|excerpt|summary|short description)\b/i', $question)
                && ! preg_match('/\b(place|page|cms)\b/i', $question),
            'set_hotel_featured' => (bool) preg_match('/\b(feature|unfeature|featured|highlight)\b/i', $question)
                && (bool) preg_match('/\b(make|mark|set|turn|toggle|add|remove|feature|unfeature|highlight)\b/i', $question)
                && (bool) preg_match('/\b(hotel|property)\b/i', $question),
            'update_place_excerpt' => (bool) preg_match('/\b(update|change|replace|rewrite|set|edit)\b/i', $question)
                && (bool) preg_match('/\b(place)\b/i', $question),
            'update_page_meta_description' => (bool) preg_match('/\b(update|change|replace|rewrite|set|edit)\b/i', $question)
                && (bool) preg_match('/\b(page|cms)\b/i', $question),
            default => false,
        };
    }

    private function finishFailed(User $user, string $proposalId, string $reason): void
    {
        DB::transaction(function () use ($user, $proposalId, $reason): void {
            $proposal = AiActionProposal::query()->whereKey($proposalId)->where('user_id', $user->id)
                ->lockForUpdate()->first();

            if (! $proposal || $proposal->status !== 'pending') {
                return;
            }

            $status = $reason === 'expired' ? 'expired' : 'failed';
            $proposal->update(['status' => $status, 'failure_reason' => $reason]);
            $this->audit->log('ai.action.'.$status, 'ai', $proposal->summary, null, null,
                ['proposal_id' => $proposal->id, 'status' => $status, 'reason' => $reason], $user);
            $proposal->conversation?->messages()->create([
                'role' => 'assistant', 'content' => 'Action not applied: '.$reason.'. Please request a fresh proposal.',
            ]);
            Log::warning('AI action not applied', ['tool' => $proposal->tool_name, 'reason' => $reason]);
        });
    }
}
