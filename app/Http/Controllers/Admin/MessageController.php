<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-way direct messaging to users. No reply thread — anything needing
 * discussion becomes a support ticket. Recipients get an in-app
 * notification plus email through their normal preferences.
 */
class MessageController extends Controller
{
    public function create(Request $request): Response
    {
        $preselected = [];

        if ($request->filled('user_id')) {
            $user = User::find($request->integer('user_id'), ['id', 'name', 'email', 'role']);

            if ($user) {
                $preselected[] = ['value' => $user->id, 'label' => $user->name, 'meta' => "{$user->email} · {$user->role}"];
            }
        }

        return Inertia::render('Admin/Messages/Create', ['preselected' => $preselected]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:50'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'action_url' => ['nullable', 'string', 'max:500', 'starts_with:/'],
        ]);

        $recipients = User::whereIn('id', $validated['user_ids'])->get();
        $sent = 0;

        foreach ($recipients as $recipient) {
            $recipient->notify(new CrmNotification('admin_message', [
                'title' => $validated['title'],
                'body' => mb_substr(trim($validated['body']), 0, 2000),
                'action_url' => $validated['action_url'] ?? null,
                'from' => $request->user()->name,
            ]));

            CommunicationLog::create([
                'recipient_user_id' => $recipient->id,
                'recipient_type' => $recipient->role,
                'channel' => 'in_app',
                'event' => 'admin_message',
                'destination_masked' => null,
                'status' => 'sent',
                'provider' => 'database',
                'sent_at' => now(),
                'created_by' => $request->user()->id,
                'metadata' => ['title' => $validated['title']],
            ]);

            $sent++;
        }

        return back()->with('flash', "Message sent to {$sent} user(s) (in-app + email per their preferences).");
    }
}
