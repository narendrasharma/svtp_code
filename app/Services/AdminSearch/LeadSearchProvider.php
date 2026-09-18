<?php

namespace App\Services\AdminSearch;

use App\Models\Lead;
use App\Models\User;

/**
 * Lead search by reference, name, phone or email. Visibility-scoped:
 * without leads.view_all only assigned leads are searchable.
 */
class LeadSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'leads';
    }

    public function label(): string
    {
        return 'Leads';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('leads.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return Lead::visibleTo($user)
            ->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            })
            ->latest()
            ->limit($limit)
            ->get(['id', 'reference', 'name', 'phone', 'status'])
            ->map(fn (Lead $lead): array => [
                'type' => 'lead',
                'label' => $lead->reference,
                'subtitle' => trim(($lead->name ?? 'Lead').' · '.($lead->phone ?? '').' · '.$lead->status),
                'url' => route('admin.leads.show', $lead, absolute: false),
                'icon' => 'bi-person-lines-fill',
            ])
            ->all();
    }
}
