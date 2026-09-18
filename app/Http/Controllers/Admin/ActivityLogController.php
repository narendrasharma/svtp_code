<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Append-only activity / audit log (11.5D).
 *
 * No delete, no edit — ordinary staff need audit.view. Filters are
 * server-side with capped pagination; change payloads were sanitized
 * at write time so the detail view is safe to render.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'event' => ['nullable', 'string', 'max:80'],
            'module' => ['nullable', 'string', 'max:40'],
            'actor_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = ActivityLog::with(['actor:id,name,email', 'impersonator:id,name,email'])
            ->latest();

        if (! empty($validated['event'])) {
            $query->where('event', 'like', '%'.$validated['event'].'%');
        }

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        if (! empty($validated['actor_id'])) {
            $query->where('actor_user_id', $validated['actor_id']);
        }

        if (! empty($validated['subject_type'])) {
            $query->where('subject_type', 'like', '%'.$validated['subject_type'].'%');
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('description', 'like', '%'.$search.'%')
                    ->orWhere('event', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%');
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'filters' => $validated,
            'modules' => $this->moduleOptions(),
        ]);
    }

    /** @return array<int, string> */
    protected function moduleOptions(): array
    {
        return ActivityLog::select('module')->distinct()->orderBy('module')->pluck('module')->all();
    }
}
