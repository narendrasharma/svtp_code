<?php

namespace App\Services\AdminSearch;

use App\Models\User;

/**
 * Contract for one global-search data source. Providers must enforce
 * their own permission + module gates and never leak sensitive fields.
 */
interface SearchProvider
{
    public function key(): string;

    public function label(): string;

    public function isAvailable(?User $user): bool;

    /**
     * @return array<int, array{type:string,label:string,subtitle:string,url:string,icon:string}>
     */
    public function search(?User $user, string $query, int $limit): array;
}
