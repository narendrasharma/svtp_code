<?php

namespace App\Services;

use App\Models\TourAddon;
use App\Models\TourPackage;

/**
 * Single server-side source of truth for TOUR booking prices.
 *
 * Controllers must never compute totals; browser-submitted amounts are
 * always ignored. Future modules (taxi, hotel) get sibling services behind
 * a resolver — this class stays tour-specific on purpose.
 */
class TourBookingPricingService
{
    /**
     * Child price as a ratio of the adult price (existing business rule).
     */
    public const CHILD_PRICE_RATIO = 0.5;

    public const DEFAULT_CURRENCY = 'INR';

    /**
     * @return array{currency: string, base_price: float, child_unit_price: float, total_adults: int, total_children: int, subtotal: float, discount_amount: float, tax_amount: float, total_amount: float}
     */
    public function quote(TourPackage $package, int $adults, int $children = 0): array
    {
        $base = round((float) $package->effective_price, 2);
        $childUnit = round($base * self::CHILD_PRICE_RATIO, 2);
        $tourBase = round($base * $adults + $childUnit * $children, 2);

        return [
            'currency' => self::DEFAULT_CURRENCY,
            'base_price' => $base,
            'child_unit_price' => $childUnit,
            'total_adults' => $adults,
            'total_children' => $children,
            'subtotal' => $tourBase,
            'discount_amount' => 0.0,
            'tax_amount' => 0.0,
            'total_amount' => $tourBase,
        ];
    }

    /**
     * Phase 10 detailed quote. Single documented order:
     *
     *   tour base + add-ons = subtotal
     *   − coupon discount = discounted subtotal
     *   + tax (0 today) = total
     *
     * Commission snapshots use total_amount as gross.
     *
     * @param  array<int, array{addon: TourAddon, quantity: int, unit_price: float, total: float}>  $addonLines
     * @return array{currency: string, base_price: float, child_unit_price: float, total_adults: int, total_children: int, tour_base: float, addons_total: float, subtotal: float, discount_amount: float, tax_amount: float, total_amount: float, addons: array}
     */
    public function quoteDetailed(
        TourPackage $package,
        int $adults,
        int $children = 0,
        array $addonLines = [],
        float $discountAmount = 0.0,
    ): array {
        $base = $this->quote($package, $adults, $children);

        $addonsTotal = round(array_sum(array_column($addonLines, 'total')), 2);
        $subtotal = round($base['subtotal'] + $addonsTotal, 2);
        $discount = round(min(max(0.0, $discountAmount), $subtotal), 2);
        $total = round($subtotal - $discount, 2);

        return [
            'currency' => self::DEFAULT_CURRENCY,
            'base_price' => $base['base_price'],
            'child_unit_price' => $base['child_unit_price'],
            'total_adults' => $adults,
            'total_children' => $children,
            'tour_base' => $base['subtotal'],
            'addons_total' => $addonsTotal,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => 0.0,
            'total_amount' => $total,
            'addons' => array_map(static fn (array $line): array => [
                'addon_id' => $line['addon']->id,
                'name' => $line['addon']->name,
                'pricing_type' => $line['addon']->pricing_type,
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'total' => $line['total'],
            ], $addonLines),
        ];
    }
}
