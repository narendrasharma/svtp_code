<?php

namespace App\Services\AdminSearch;

use App\Models\Coupon;
use App\Models\User;

/**
 * Coupon search by code or title. Amounts stay out of the payload —
 * the edit page is one click away for authorized staff.
 */
class CouponSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'coupons';
    }

    public function label(): string
    {
        return 'Coupons';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('marketing.coupons');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return Coupon::query()
            ->where(function ($q) use ($term): void {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            })
            ->orderBy('code')
            ->limit($limit)
            ->get(['id', 'code', 'name'])
            ->map(fn (Coupon $coupon): array => [
                'type' => 'coupon',
                'label' => $coupon->code,
                'subtitle' => 'Coupon · '.($coupon->name ?? $coupon->code),
                'url' => route('admin.coupons.edit', $coupon, absolute: false),
                'icon' => 'bi-ticket-perforated',
            ])
            ->all();
    }
}
