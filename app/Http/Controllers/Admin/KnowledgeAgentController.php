<?php

namespace App\Http\Controllers\Admin;

use App\AI\Support\ActionException;
use App\AI\Support\ActionProposalService;
use App\AI\Support\AIException;
use App\AI\Support\AIManager;
use App\AI\Support\AISettings;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\KnowledgeAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KnowledgeAgentController extends Controller
{
    public function index(AIManager $ai, AISettings $settings): Response
    {
        return Inertia::render('Admin/AiAssistant/Index', [
            'agent' => [
                'available' => $ai->available() && $this->supportsTools($ai),
                'provider' => $settings->providerKey(),
                'model' => $settings->model($settings->providerKey()),
                'actions_enabled' => $settings->enabled() && $settings->agentActionsEnabled(),
            ],
            'conversations' => AiConversation::query()->where('user_id', auth()->id())
                ->latest('updated_at')->limit(50)->get(['id', 'title', 'updated_at']),
            'canManageKnowledge' => auth()->user()->hasStaffPermission('ai.knowledge.manage'),
        ]);
    }

    public function show(Request $request, int $conversation, ActionProposalService $actions): JsonResponse
    {
        $owned = $this->owned($request, $conversation);

        return response()->json([
            'conversation' => $owned->only(['id', 'title', 'updated_at']),
            'messages' => $owned->messages()->orderBy('id')->limit(200)->get(['id', 'role', 'content', 'created_at']),
            'tools_used' => $owned->toolEvents()->select('tool_name')->distinct()->pluck('tool_name'),
            'proposals' => $owned->actionProposals()->latest()->limit(50)->get()->map(fn ($proposal): array => $actions->present($proposal)),
        ]);
    }

    public function rename(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160']]);
        $owned = $this->owned($request, $conversation);
        $owned->update(['title' => trim($data['title'])]);

        return response()->json(['id' => $owned->id, 'title' => $owned->title]);
    }

    public function destroy(Request $request, int $conversation): JsonResponse
    {
        $this->owned($request, $conversation)->delete();

        return response()->json(['deleted' => true]);
    }

    public function message(Request $request, KnowledgeAgentService $agent, ActionProposalService $actions): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $conversation = isset($data['conversation_id']) ? $this->owned($request, (int) $data['conversation_id']) : null;
        $history = $conversation?->messages()->latest('id')->limit(6)->get(['role', 'content'])->reverse()
            ->map(fn ($message): array => ['role' => $message->role, 'content' => $message->content])->values()->all() ?? [];

        try {
            $result = $agent->answer($request->user(), $data['question'], $history);
        } catch (AIException|ActionException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }

        try {
            [$conversation, $proposal] = DB::transaction(function () use ($request, $data, $result, $conversation, $actions): array {
                $conversation ??= AiConversation::create([
                    'user_id' => $request->user()->id,
                    'title' => mb_substr(trim($data['question']), 0, 120),
                ]);
                $proposal = isset($result['proposal_request'])
                    ? $actions->persist($request->user(), $conversation, $result['proposal_request']) : null;
                $conversation->messages()->createMany([
                    ['role' => 'user', 'content' => $data['question']],
                    ['role' => 'assistant', 'content' => $result['answer']],
                ]);

                foreach ($result['tool_events'] ?? [] as $event) {
                    $conversation->toolEvents()->create($event + ['created_at' => now()]);
                }

                $conversation->update(['provider' => $result['provider'], 'model' => $result['model'], 'updated_at' => now()]);

                return [$conversation, $proposal];
            });
        } catch (ActionException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        } catch (Throwable $exception) {
            Log::error('AI conversation persistence failed', [
                'use_case' => 'knowledge_agent', 'exception_class' => $exception::class,
            ]);

            $message = isset($result['proposal_request'])
                ? 'Could not create the action proposal. No change was made.'
                : 'The assistant could not save this response. Please try again.';

            return response()->json(['message' => $message], 503);
        }

        return response()->json([
            'conversation' => $conversation->only(['id', 'title', 'updated_at']),
            'answer' => $result['answer'], 'tools_used' => $result['tools_used'], 'sources' => $result['sources'] ?? [],
            'provider' => $result['provider'], 'model' => $result['model'],
            'proposal' => $proposal ? $actions->present($proposal) : null,
        ]);
    }

    private function owned(Request $request, int $id): AiConversation
    {
        return AiConversation::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function supportsTools(AIManager $ai): bool
    {
        try {
            return $ai->capabilities()->supports('tool_calling');
        } catch (AIException) {
            return false;
        }
    }
}
