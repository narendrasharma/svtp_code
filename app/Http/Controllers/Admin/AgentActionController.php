<?php

namespace App\Http\Controllers\Admin;

use App\AI\Support\ActionException;
use App\AI\Support\ActionProposalService;
use App\Http\Controllers\Controller;
use App\Models\AiActionProposal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AgentActionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'executed', 'rejected', 'expired', 'failed'])],
            'tool' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $canViewAll = $request->user()->hasStaffPermission('audit.view');
        $query = AiActionProposal::query()->with('user:id,name')->latest();

        if (! $canViewAll) {
            $query->where('user_id', $request->user()->id);
            unset($filters['user_id']);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        foreach (['status' => 'status', 'tool' => 'tool_name'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $proposals = $query->paginate(20)->withQueryString();
        $proposals->through(fn (AiActionProposal $proposal): array => [
            'id' => $proposal->id, 'created_at' => $proposal->created_at->toIso8601String(),
            'user' => $proposal->user?->name, 'tool_name' => $proposal->tool_name,
            'target_type' => $proposal->target_type, 'target_id' => $proposal->target_id,
            'target_label' => $proposal->target_label, 'status' => $proposal->status,
            'failure_reason' => $proposal->failure_reason,
            'conversation_id' => $proposal->ai_conversation_id,
        ]);

        return Inertia::render('Admin/AiAssistant/Actions', [
            'proposals' => $proposals, 'filters' => $filters,
            'canViewAll' => $canViewAll,
        ]);
    }

    public function confirm(Request $request, string $proposal, ActionProposalService $actions): JsonResponse
    {
        try {
            return response()->json($actions->confirm($request->user(), $proposal));
        } catch (ActionException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Action proposal not found.'], 404);
        }
    }

    public function reject(Request $request, string $proposal, ActionProposalService $actions): JsonResponse
    {
        try {
            return response()->json(['proposal' => $actions->reject($request->user(), $proposal)]);
        } catch (ActionException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Action proposal not found.'], 404);
        }
    }
}
