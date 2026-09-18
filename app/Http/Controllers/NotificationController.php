<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated notification center (Phase 9).
 *
 * Users see only their own notifications; action URLs are plain links that
 * resolve through normal policies (a link never grants access by itself).
 * One page serves every role with a role-appropriate layout.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'role' => $request->user()->role,
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_type === $request->user()->getMorphClass()
            && (int) $notification->notifiable_id === (int) $request->user()->id,
            404
        );

        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('flash', 'All notifications marked as read.');
    }

    /**
     * Recent items for the topbar dropdown. Same ownership scope as the
     * page — shapes only title/message/time/unread/action_url.
     */
    public function recent(Request $request): JsonResponse
    {
        $items = $request->user()->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'action_url' => $notification->data['action_url'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at,
            ]);

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }
}
