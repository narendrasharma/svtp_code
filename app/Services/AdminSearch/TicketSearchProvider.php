<?php

namespace App\Services\AdminSearch;

use App\Models\SupportTicket;
use App\Models\User;

/**
 * Support ticket search by reference, subject or requester name.
 * Visibility-scoped like the desk itself.
 */
class TicketSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'tickets';
    }

    public function label(): string
    {
        return 'Support tickets';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('support.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return SupportTicket::visibleTo($user)
            ->with('requester:id,name')
            ->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhereHas('requester', fn ($r) => $r->where('name', 'like', $term));
            })
            ->latest()
            ->limit($limit)
            ->get(['id', 'reference', 'subject', 'status', 'requester_user_id'])
            ->map(fn (SupportTicket $ticket): array => [
                'type' => 'ticket',
                'label' => $ticket->reference,
                'subtitle' => trim(($ticket->subject ?? '').' · '.$ticket->status),
                'url' => route('admin.support.show', $ticket, absolute: false),
                'icon' => 'bi-life-preserver',
            ])
            ->all();
    }
}
