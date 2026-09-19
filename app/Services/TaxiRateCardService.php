<?php

namespace App\Services;

use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Models\TaxiRateCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxiRateCardService
{
    public function toggle(TaxiRateCard $rateCard): TaxiRateCard
    {
        if (! $rateCard->is_active) {
            $this->assertNoOverlap($rateCard, [
                'vendor_profile_id' => $rateCard->vendor_profile_id,
                'vehicle_type_id' => $rateCard->vehicle_type_id,
                'trip_type' => $rateCard->trip_type->value,
                'currency' => $rateCard->currency,
                'is_active' => true,
                'effective_from' => $rateCard->effective_from,
                'effective_until' => $rateCard->effective_until,
            ]);
        }

        $rateCard->update(['is_active' => ! $rateCard->is_active]);

        return $rateCard->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?TaxiRateCard $rateCard, array $data): TaxiRateCard
    {
        $this->assertRuleCompatibility($data['rules'] ?? []);
        $this->assertNoOverlap($rateCard, $data);

        return DB::transaction(function () use ($rateCard, $data): TaxiRateCard {
            $attributes = [
                'vendor_profile_id' => $data['vendor_profile_id'] ?? null,
                'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
                'name' => trim((string) $data['name']),
                'trip_type' => $data['trip_type'],
                'currency' => strtoupper((string) $data['currency']),
                'is_active' => (bool) $data['is_active'],
                'effective_from' => $data['effective_from'] ?? null,
                'effective_until' => $data['effective_until'] ?? null,
            ];

            if ($rateCard) {
                $rateCard->update($attributes);
            } else {
                $rateCard = TaxiRateCard::create($attributes);
            }

            $ruleIds = [];

            foreach ($data['rules'] ?? [] as $ruleData) {
                $rule = $rateCard->rules()->updateOrCreate(
                    ['code' => $ruleData['code']],
                    [
                        'calculation_type' => $ruleData['calculation_type'],
                        'amount' => $ruleData['amount'],
                        'included_quantity' => $ruleData['included_quantity'] ?? null,
                        'unit' => $ruleData['unit'] ?? null,
                        'configuration' => $ruleData['configuration'] ?? null,
                    ],
                );
                $ruleIds[] = $rule->id;
            }

            $ruleIds === []
                ? $rateCard->rules()->delete()
                : $rateCard->rules()->whereNotIn('id', $ruleIds)->delete();

            $packageIds = [];

            foreach ($data['packages'] ?? [] as $packageData) {
                $package = isset($packageData['id'])
                    ? $rateCard->rentalPackages()->whereKey($packageData['id'])->firstOrFail()
                    : $rateCard->rentalPackages()->make();
                $package->fill([
                    'name' => trim((string) $packageData['name']),
                    'included_hours' => $packageData['included_hours'],
                    'included_km' => $packageData['included_km'],
                    'package_price' => $packageData['package_price'],
                    'extra_km_rate' => $packageData['extra_km_rate'],
                    'extra_hour_rate' => $packageData['extra_hour_rate'],
                    'is_active' => (bool) $packageData['is_active'],
                    'sort_order' => $packageData['sort_order'] ?? 0,
                ])->save();
                $packageIds[] = $package->id;
            }

            $packageIds === []
                ? $rateCard->rentalPackages()->delete()
                : $rateCard->rentalPackages()->whereNotIn('id', $packageIds)->delete();

            return $rateCard->refresh()->load(['rules', 'rentalPackages']);
        });
    }

    /** @param array<int, array<string, mixed>> $rules */
    private function assertRuleCompatibility(array $rules): void
    {
        $allowed = [
            TaxiRateRuleCode::BaseFare->value => [TaxiRateCalculationType::Fixed],
            TaxiRateRuleCode::MinimumFare->value => [TaxiRateCalculationType::Fixed],
            TaxiRateRuleCode::DistanceRate->value => [TaxiRateCalculationType::PerKilometer],
            TaxiRateRuleCode::ExtraDistanceRate->value => [TaxiRateCalculationType::PerKilometer],
            TaxiRateRuleCode::DriverAllowance->value => [TaxiRateCalculationType::Fixed, TaxiRateCalculationType::PerDay],
            TaxiRateRuleCode::NightCharge->value => [TaxiRateCalculationType::Fixed, TaxiRateCalculationType::Percentage],
            TaxiRateRuleCode::WaitingCharge->value => [TaxiRateCalculationType::Fixed, TaxiRateCalculationType::PerHour],
            TaxiRateRuleCode::Toll->value => [TaxiRateCalculationType::Fixed, TaxiRateCalculationType::Actual, TaxiRateCalculationType::Included],
            TaxiRateRuleCode::Parking->value => [TaxiRateCalculationType::Fixed, TaxiRateCalculationType::Actual, TaxiRateCalculationType::Included],
            TaxiRateRuleCode::Tax->value => [TaxiRateCalculationType::Fixed, TaxiRateCalculationType::Percentage],
        ];

        foreach ($rules as $index => $rule) {
            $type = TaxiRateCalculationType::tryFrom((string) ($rule['calculation_type'] ?? ''));

            if (! $type || ! in_array($type, $allowed[$rule['code']] ?? [], true)) {
                throw ValidationException::withMessages([
                    "rules.{$index}.calculation_type" => 'This calculation type is not valid for the selected pricing rule.',
                ]);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function assertNoOverlap(?TaxiRateCard $rateCard, array $data): void
    {
        if (! ($data['is_active'] ?? false)) {
            return;
        }

        $query = TaxiRateCard::query()
            ->where('trip_type', $data['trip_type'])
            ->where('currency', strtoupper((string) $data['currency']));

        isset($data['vendor_profile_id'])
            ? $query->where('vendor_profile_id', $data['vendor_profile_id'])
            : $query->whereNull('vendor_profile_id');
        isset($data['vehicle_type_id'])
            ? $query->where('vehicle_type_id', $data['vehicle_type_id'])
            : $query->whereNull('vehicle_type_id');

        if ($rateCard) {
            $query->whereKeyNot($rateCard->id);
        }

        $from = $data['effective_from'] ?? null;
        $until = $data['effective_until'] ?? null;

        if ($from) {
            $query->where(fn ($end) => $end->whereNull('effective_until')->orWhere('effective_until', '>=', $from));
        }

        if ($until) {
            $query->where(fn ($start) => $start->whereNull('effective_from')->orWhere('effective_from', '<=', $until));
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => 'An active rate card already covers this scope, trip type, currency, and effective period.',
            ]);
        }
    }
}
