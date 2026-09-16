<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Rules\MenuLinkUrl;
use Illuminate\Support\Collection;

class PublicMenuService
{
    /** @return array{header: array, footer: array} */
    public function navigation(): array
    {
        $navigation = ['header' => [], 'footer' => []];
        $menus = Menu::where('is_active', true)
            ->whereIn('location', ['header', 'footer'])
            ->orderBy('sort_order')->orderBy('id')
            ->with(['items' => fn ($query) => $query->where('is_active', true)->orderBy('id'), 'items.page:id,slug,is_active'])
            ->get();

        foreach ($menus as $menu) {
            if ($navigation[$menu->location] !== []) {
                continue;
            }
            $navigation[$menu->location] = $this->tree($menu->items->groupBy('parent_id'));
        }

        return $navigation;
    }

    private function tree(Collection $groups, ?int $parentId = null, array $ancestors = []): array
    {
        $result = [];
        foreach ($groups->get($parentId ?? '', collect()) as $item) {
            if (isset($ancestors[$item->id])) {
                continue;
            }
            $href = $this->resolveUrl($item);
            if ($href === null || trim($item->title) === '') {
                continue;
            }
            $result[] = [
                'id' => $item->id,
                'label' => $item->title,
                'href' => $href,
                'target' => $item->target === '_blank' ? '_blank' : '_self',
                'children' => $this->tree($groups, $item->id, $ancestors + [$item->id => true]),
            ];
        }

        return $result;
    }

    private function resolveUrl(MenuItem $item): ?string
    {
        if ($item->type === 'page') {
            return $item->page?->is_active ? $this->applicationUrl(route('page.show', ['slug' => $item->page->slug], absolute: false)) : null;
        }
        if ($item->type !== 'custom' || ! MenuLinkUrl::isSafe($item->url)) {
            return null;
        }
        $url = $item->url;
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return $url;
        }

        return $this->applicationUrl($url);
    }

    private function applicationUrl(string $url): string
    {
        $root = rtrim(config('app.url'), '/');
        $basePath = parse_url($root, PHP_URL_PATH) ?: '';
        if ($basePath !== '' && ($url === $basePath || str_starts_with($url, $basePath.'/') || str_starts_with($url, $basePath.'?') || str_starts_with($url, $basePath.'#'))) {
            return substr($root, 0, -strlen($basePath)).$url;
        }

        return $root.'/'.ltrim($url, '/');
    }
}
