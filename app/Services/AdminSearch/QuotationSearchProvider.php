<?php

namespace App\Services\AdminSearch;

use App\Models\Quotation;
use App\Models\User;

/**
 * Quotation search by reference (revision shown in the label).
 */
class QuotationSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'quotations';
    }

    public function label(): string
    {
        return 'Quotations';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('quotations.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return Quotation::query()
            ->with(['lead:id,name', 'customer:id,name'])
            ->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhereHas('lead', fn ($l) => $l->where('name', 'like', $term))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Quotation $quotation): array => [
                'type' => 'quotation',
                'label' => $quotation->displayReference(),
                'subtitle' => trim(($quotation->lead?->name ?? $quotation->customer?->name ?? 'Quotation').' · '.$quotation->status),
                'url' => route('admin.quotations.show', $quotation, absolute: false),
                'icon' => 'bi-file-earmark-text',
            ])
            ->all();
    }
}
