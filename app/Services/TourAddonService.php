<?php

namespace App\Services;

use App\Models\TourAddon;
use App\Models\TourPackage;
use Illuminate\Validation\ValidationException;

/**
 * Tour add-on pricing (Phase 10).
 *
 * Server-authoritative: unit prices always come from live TourAddon rows,
 * quantities are validated, wrong-tour selections are rejected and
 * required add-ons are auto-applied. BookingService snapshots the result
 * into booking_addons so later edits never rewrite history.
 */
class TourAddonService
{
    /**
     * Resolve raw client selections into priced lines.
     *
     * @param  array<int, array{addon_id: int, quantity?: int}>  $selections
     * @return array{lines: array<int, array{addon: TourAddon, quantity: int, unit_price: float, total: float}>, total: float}
     */
    public function resolve(TourPackage $package, array $selections, int $adults, int $children): array
    {
        $normalized = $this->normalizeSelections($selections);

        $available = $package->addons()->where('is_active', true)->get()->keyBy('id');

        // Wrong-tour guard: every requested id must belong to this tour.
        foreach (array_keys($normalized) as $addonId) {
            if (! $available->has($addonId)) {
                throw ValidationException::withMessages(['addons' => 'One of the selected extras is not available for this tour.']);
            }
        }

        $guests = max(0, $adults + $children);
        $lines = [];

        foreach ($available as $addonId => $addon) {
            /** @var TourAddon $addon */
            $quantity = $normalized[$addonId] ?? null;

            if ($addon->is_required && $quantity === null) {
                $quantity = $this->defaultQuantity($addon);
            }

            if ($quantity === null) {
                continue;
            }

            $this->assertQuantity($addon, $quantity);

            $unit = round((float) $addon->price, 2);
            $total = $this->lineTotal($addon, $unit, $quantity, $guests);

            $lines[] = [
                'addon' => $addon,
                'quantity' => $quantity,
                'unit_price' => $unit,
                'total' => $total,
            ];
        }

        $total = round(array_sum(array_column($lines, 'total')), 2);

        return ['lines' => $lines, 'total' => $total];
    }

    /**
     * Required add-ons with no client selection still apply.
     *
     * @return array{lines: array<int, array{addon: TourAddon, quantity: int, unit_price: float, total: float}>, total: float}
     */
    public function requiredOnly(TourPackage $package, int $adults, int $children): array
    {
        return $this->resolve($package, [], $adults, $children);
    }

    /**
     * @param  array<int, array{addon: TourAddon, quantity: int, unit_price: float, total: float}>  $lines
     * @return array<int, array{tour_addon_id: ?int, name: string, pricing_type: string, unit_price: string, quantity: int, total_amount: string}>
     */
    public function snapshotLines(array $lines): array
    {
        return array_map(static function (array $line): array {
            /** @var TourAddon $addon */
            $addon = $line['addon'];

            return [
                'tour_addon_id' => $addon->id,
                'name' => $addon->name,
                'pricing_type' => $addon->pricing_type,
                'unit_price' => number_format($line['unit_price'], 2, '.', ''),
                'quantity' => $line['quantity'],
                'total_amount' => number_format($line['total'], 2, '.', ''),
            ];
        }, $lines);
    }

    /**
     * @param  array<int, mixed>  $selections
     * @return array<int, int>
     */
    protected function normalizeSelections(array $selections): array
    {
        $normalized = [];

        foreach ($selections as $selection) {
            if (! is_array($selection) || ! isset($selection['addon_id'])) {
                continue;
            }

            $addonId = (int) $selection['addon_id'];

            if ($addonId <= 0) {
                continue;
            }

            $quantity = isset($selection['quantity']) ? (int) $selection['quantity'] : 1;

            // Last occurrence wins; duplicates cannot double-charge.
            $normalized[$addonId] = $quantity;
        }

        return $normalized;
    }

    protected function defaultQuantity(TourAddon $addon): int
    {
        return 1;
    }

    /**
     * @throws ValidationException
     */
    protected function assertQuantity(TourAddon $addon, int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['addons' => "Invalid quantity for {$addon->name}."]);
        }

        if ($addon->pricing_type === TourAddon::PRICING_FIXED && $quantity !== 1) {
            throw ValidationException::withMessages(['addons' => "Invalid quantity for {$addon->name}."]);
        }

        if ($addon->max_quantity !== null && $quantity > (int) $addon->max_quantity) {
            throw ValidationException::withMessages(['addons' => "Only {$addon->max_quantity} × {$addon->name} allowed per booking."]);
        }

        if ($quantity > 30) {
            throw ValidationException::withMessages(['addons' => "Invalid quantity for {$addon->name}."]);
        }
    }

    protected function lineTotal(TourAddon $addon, float $unit, int $quantity, int $guests): float
    {
        if ($addon->pricing_type === TourAddon::PRICING_PER_PERSON) {
            return round($unit * max(1, $guests), 2);
        }

        if ($addon->pricing_type === TourAddon::PRICING_PER_QUANTITY) {
            return round($unit * $quantity, 2);
        }

        // Fixed: charged once per booking regardless of guests.
        return round($unit, 2);
    }
}
