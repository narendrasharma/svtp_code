<?php

namespace App\Http\Controllers\Admin;

use App\AI\Support\AIException;
use App\AI\Support\AISettings;
use App\AI\Support\KnowledgeIndexingService;
use App\Http\Controllers\Controller;
use App\Models\AiKnowledgeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeController extends Controller
{
    public function index(Request $request, AISettings $settings): Response
    {
        $this->authorizeManage($request);
        $provider = $settings->embeddingProviderKey();
        $model = $settings->embeddingModel();
        $currentChunks = fn ($query) => $query->where('embedding_provider', $provider)->where('embedding_model', $model);

        return Inertia::render('Admin/AiAssistant/Knowledge', [
            'documents' => AiKnowledgeDocument::query()->latest('updated_at')->limit(50)
                ->select(['id', 'title', 'source_type', 'source_id', 'content', 'status', 'visibility', 'locale', 'last_indexed_at'])
                ->withCount(['chunks as current_index_chunks_count' => $currentChunks])->get(),
            'embeddingStatus' => [
                'provider' => $provider,
                'model' => $model,
                'configured' => $settings->hasCredential($provider),
                'enabled' => $settings->enabled() && $settings->knowledgeEnabled(),
                'needs_reindex' => AiKnowledgeDocument::query()->where('status', 'active')
                    ->whereDoesntHave('chunks', $currentChunks)->count(),
            ],
        ]);
    }

    public function store(Request $request, KnowledgeIndexingService $indexer): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:'.KnowledgeIndexingService::MAX_DOCUMENT_LENGTH],
            'locale' => ['nullable', 'string', 'max:12'],
            'visibility' => ['required', Rule::in(['internal', 'public'])],
        ]);
        $document = AiKnowledgeDocument::create($data + [
            'source_type' => 'manual', 'status' => 'inactive', 'created_by' => $request->user()->id,
        ]);

        return $this->attemptIndex($indexer, $document);
    }

    public function update(Request $request, AiKnowledgeDocument $document, KnowledgeIndexingService $indexer): RedirectResponse
    {
        $this->authorizeManage($request);
        abort_unless($document->source_type === 'manual', 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:'.KnowledgeIndexingService::MAX_DOCUMENT_LENGTH],
            'locale' => ['nullable', 'string', 'max:12'],
            'visibility' => ['required', Rule::in(['internal', 'public'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $changed = $document->title !== $data['title'] || $document->content !== $data['content'];
        $document->fill($data);

        if ($changed || $data['status'] === 'inactive') {
            $document->status = 'inactive';
            $document->chunks()->delete();
        }

        $document->save();

        return $data['status'] === 'active' ? $this->attemptIndex($indexer, $document) : back()->with('flash', 'Knowledge article saved as inactive.');
    }

    public function reindex(Request $request, AiKnowledgeDocument $document, KnowledgeIndexingService $indexer): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($document->source_type !== 'manual') {
            try {
                $document = $indexer->import($document->source_type, (int) $document->source_id, $request->user()->id);
            } catch (AIException $exception) {
                return back()->withErrors(['knowledge' => $exception->getMessage()]);
            }

            return back()->with('flash', 'Source reindexed.');
        }

        return $this->attemptIndex($indexer, $document);
    }

    public function destroy(Request $request, AiKnowledgeDocument $document): RedirectResponse
    {
        $this->authorizeManage($request);
        $document->delete();

        return back()->with('flash', 'Knowledge document deleted.');
    }

    public function import(Request $request, KnowledgeIndexingService $indexer): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'source_type' => ['required', Rule::in(['page', 'destination', 'place'])],
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = $indexer->importBatch($data['source_type'], $data['after_id'] ?? null, $request->user()->id);
        } catch (AIException $exception) {
            return back()->withErrors(['knowledge' => $exception->getMessage()]);
        }

        return back()->with('flash', "Indexed {$result['processed']} sources in this batch."
            .($result['next_id'] ? " Continue after ID {$result['next_id']}." : ' Import complete.'));
    }

    private function attemptIndex(KnowledgeIndexingService $indexer, AiKnowledgeDocument $document): RedirectResponse
    {
        try {
            $result = $indexer->index($document);
        } catch (AIException $exception) {
            return back()->withErrors(['knowledge' => $exception->getMessage()]);
        }

        return back()->with('flash', $result['indexed'] ? 'Knowledge document indexed.' : 'Knowledge document is unchanged.');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->isAdmin() && $request->user()->hasStaffPermission('ai.knowledge.manage'), 403);
    }
}
