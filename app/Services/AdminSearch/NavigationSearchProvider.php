<?php

namespace App\Services\AdminSearch;

use App\Models\User;
use App\Support\AdminNavigation;

/**
 * Navigation results for the command palette, resolved from the same
 * registry (and the same permission/module filtering) as the sidebar.
 */
class NavigationSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'navigation';
    }

    public function label(): string
    {
        return 'Navigation';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->isAdmin();
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        return AdminNavigation::searchFiltered(
            AdminNavigation::filteredFor($user),
            $query,
            $limit
        );
    }
}
