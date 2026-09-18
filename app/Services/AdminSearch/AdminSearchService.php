<?php

namespace App\Services\AdminSearch;

use App\Models\User;

/**
 * Central global admin search (Phase 11.5A).
 *
 * Thin orchestrator — one small provider per domain, each enforcing its
 * own permission/module gates. No giant controller, no sensitive fields,
 * no unindexed full-table scans (all LIKEs hit indexed name/email/
 * reference columns with tight per-provider limits).
 */
class AdminSearchService
{
    /**
     * @return array<int, SearchProvider>
     */
    public function providers(): array
    {
        return [
            new NavigationSearchProvider,
            new BookingSearchProvider,
            new LeadSearchProvider,
            new QuotationSearchProvider,
            new PaymentSearchProvider,
            new TicketSearchProvider,
            new UserSearchProvider,
            new VendorSearchProvider,
            new TourSearchProvider,
            new TaxiBookingSearchProvider,
            new TaxiDriverSearchProvider,
            new TaxiVehicleSearchProvider,
            new ContentSearchProvider,
            new CouponSearchProvider,
        ];
    }

    /**
     * @return array{query:string, groups: array<int, array{key:string,label:string,items:array<int,array<string,mixed>>}>}
     */
    public function search(?User $user, string $query, int $perProvider = 5): array
    {
        $query = trim(mb_substr($query, 0, 80));

        if (mb_strlen($query) < 2) {
            return ['query' => $query, 'groups' => []];
        }

        $groups = [];

        foreach ($this->providers() as $provider) {
            if (! $provider->isAvailable($user)) {
                continue;
            }

            $items = $provider->search($user, $query, $perProvider);

            if ($items !== []) {
                $groups[] = [
                    'key' => $provider->key(),
                    'label' => $provider->label(),
                    'items' => $items,
                ];
            }
        }

        return ['query' => $query, 'groups' => $groups];
    }
}
