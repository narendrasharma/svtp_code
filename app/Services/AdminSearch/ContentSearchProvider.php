<?php

namespace App\Services\AdminSearch;

use App\Models\Page;
use App\Models\User;

/**
 * CMS page search (title/slug). Coupons are marketing entities and are
 * covered by CouponSearchProvider.
 */
class ContentSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return 'Pages';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('content.pages');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return Page::query()
            ->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('slug', 'like', $term);
            })
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'slug'])
            ->map(fn (Page $page): array => [
                'type' => 'page',
                'label' => $page->title,
                'subtitle' => 'Page · /'.$page->slug,
                'url' => route('admin.pages.edit', $page, absolute: false),
                'icon' => 'bi-file-earmark-text',
            ])
            ->all();
    }
}
