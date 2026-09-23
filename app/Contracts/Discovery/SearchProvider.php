<?php

namespace App\Contracts\Discovery;

use Illuminate\Support\Collection;

/**
 * Location search provider seam (Phase 13C).
 *
 * Default driver is `database`. Future CodeCanyon extensions may add
 * Scout/Meilisearch/Algolia adapters behind this contract without
 * touching callers. No external infrastructure is required.
 */
interface SearchProvider
{
    public function key(): string;

    /**
     * Type-tagged, bounded location suggestions for an already
     * normalized term. Providers must honour public visibility scopes
     * and module gating; they must never leak private fields.
     *
     * @return Collection<int, array{type:string,id:int,name:string,subtitle:?string,slug:string,image:?string,url:string,coordinates:?array{lat:?float,lng:?float},label:string}>
     */
    public function suggest(string $term, int $limit, ?string $locale = null): Collection;
}
