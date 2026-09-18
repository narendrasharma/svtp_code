<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommunicationChannel;
use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Communication audit log. Destinations are masked at write time; this
 * page only ever reads them back.
 */
class CommunicationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CommunicationLog::query()
            ->with(['recipient:id,name,email', 'creator:id,name'])
            ->latest();

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($q) => $q
                ->where('template_key', 'like', $term)
                ->where('event', 'like', $term)
                ->orWhere('destination_masked', 'like', $term)
                ->orWhereHas('recipient', fn ($r) => $r->where('name', 'like', $term)));
        }

        if ($request->filled('channel') && in_array($request->string('channel')->toString(), array_column(CommunicationChannel::cases(), 'value'), true)) {
            $query->where('channel', $request->string('channel')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return Inertia::render('Admin/CommunicationLogs/Index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'channel', 'status']),
            'channels' => collect(CommunicationChannel::cases())->map(fn ($c): array => ['value' => $c->value, 'label' => $c->label()]),
        ]);
    }
}
