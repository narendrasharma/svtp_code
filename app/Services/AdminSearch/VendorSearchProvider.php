<?php

namespace App\Services\AdminSearch;

use App\Models\User;
use App\Models\VendorProfile;

/**
 * Vendor search by business name, email, phone or city. Returns public
 * business descriptors only — never bank/payout internals.
 */
class VendorSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'vendors';
    }

    public function label(): string
    {
        return 'Vendors';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('vendors.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return VendorProfile::query()
            ->where(function ($q) use ($term): void {
                $q->where('business_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('city', 'like', $term);
            })
            ->orderBy('business_name')
            ->limit($limit)
            ->get(['id', 'user_id', 'business_name', 'city', 'is_active'])
            ->map(fn (VendorProfile $profile): array => [
                'type' => 'vendor',
                'label' => $profile->business_name,
                'subtitle' => trim('Vendor · '.($profile->city ?? '').($profile->is_active ? '' : ' · inactive')),
                'url' => route('admin.users.show', $profile->user_id, absolute: false),
                'icon' => 'bi-shop',
            ])
            ->all();
    }
}
