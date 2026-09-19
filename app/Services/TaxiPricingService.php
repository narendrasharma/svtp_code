<?php

namespace App\Services;

use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Enums\TripType;
use App\Models\TaxiRateCard;
use App\Models\TaxiRateRule;
use App\Models\TaxiRentalPackage;
use App\Support\TaxiSettings;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TaxiPricingService
{
    private const CALCULATION_SCALE = 6;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        $tripType = $this->tripType($input['trip_type'] ?? null);
        $currency = strtoupper((string) ($input['currency'] ?? TaxiSettings::get('taxi.default_currency') ?? 'INR'));
        $vendorProfileId = $this->nullablePositiveInteger($input['vendor_profile_id'] ?? null, 'vendor_profile_id');
        $vehicleTypeId = $this->nullablePositiveInteger($input['vehicle_type_id'] ?? null, 'vehicle_type_id');
        $rateCard = $this->resolveRateCard($tripType, $currency, $vendorProfileId, $vehicleTypeId);
        $rules = $rateCard->rules->keyBy(fn (TaxiRateRule $rule): string => $rule->code->value);

        $distance = $this->nonNegativeDecimal($input['distance_km'] ?? $input['quoted_distance_km'] ?? 0, 'distance_km');
        $durationMinutes = $this->nonNegativeInteger($input['duration_minutes'] ?? $input['quoted_duration_minutes'] ?? 0, 'duration_minutes');
        $waitingMinutes = $this->nonNegativeInteger($input['waiting_minutes'] ?? 0, 'waiting_minutes');
        $tripDays = $this->tripDays($tripType, $input['pickup_at'] ?? null, $input['return_at'] ?? null);

        $baseFare = $this->fixedRuleAmount($rules, TaxiRateRuleCode::BaseFare);
        $distanceCharge = '0.000000';
        $rentalCharge = '0.000000';
        $extraKmCharge = '0.000000';
        $extraHourCharge = '0.000000';
        $rentalPackage = null;

        if ($tripType === TripType::Hourly) {
            $rentalPackage = $this->resolveRentalPackage($rateCard, $input['rental_package_id'] ?? null);
            $rentalCharge = $this->decimal($rentalPackage->package_price);
            $extraDistance = $this->positiveDifference($distance, $this->decimal($rentalPackage->included_km));
            $extraKmCharge = bcmul($extraDistance, $this->decimal($rentalPackage->extra_km_rate), self::CALCULATION_SCALE);
            $durationHours = bcdiv((string) $durationMinutes, '60', self::CALCULATION_SCALE);
            $extraHours = $this->positiveDifference($durationHours, $this->decimal($rentalPackage->included_hours));
            $extraHourCharge = bcmul($extraHours, $this->decimal($rentalPackage->extra_hour_rate), self::CALCULATION_SCALE);
        } else {
            [$distanceCharge, $extraKmCharge] = $this->distanceCharges($rules, $distance, $tripDays);
        }

        $fareBeforeMinimum = $this->sum([$baseFare, $distanceCharge, $rentalCharge, $extraKmCharge, $extraHourCharge]);
        $minimumFare = $this->fixedRuleAmount($rules, TaxiRateRuleCode::MinimumFare);
        $minimumFareAdjustment = bccomp($minimumFare, $fareBeforeMinimum, self::CALCULATION_SCALE) === 1
            ? bcsub($minimumFare, $fareBeforeMinimum, self::CALCULATION_SCALE)
            : '0.000000';
        $coreFare = bcadd($fareBeforeMinimum, $minimumFareAdjustment, self::CALCULATION_SCALE);

        $driverAllowance = $this->allowance($rules->get(TaxiRateRuleCode::DriverAllowance->value), $tripDays);
        $nightCharge = $this->nightCharge($rules->get(TaxiRateRuleCode::NightCharge->value), $coreFare, $input['pickup_at'] ?? null);
        $waitingCharge = $this->waitingCharge($rules->get(TaxiRateRuleCode::WaitingCharge->value), $waitingMinutes);
        $authorizedActuals = ($input['authorized_actual_costs'] ?? false) === true;
        $toll = $this->passThroughCharge($rules->get(TaxiRateRuleCode::Toll->value), $input['toll_amount'] ?? 0, $authorizedActuals, 'toll_amount');
        $parking = $this->passThroughCharge($rules->get(TaxiRateRuleCode::Parking->value), $input['parking_amount'] ?? 0, $authorizedActuals, 'parking_amount');
        $subtotal = $this->sum([$coreFare, $driverAllowance, $nightCharge, $waitingCharge, $toll, $parking]);
        $tax = $this->tax($rules->get(TaxiRateRuleCode::Tax->value), $subtotal);
        $grandTotal = bcadd($subtotal, $tax, self::CALCULATION_SCALE);

        $breakdown = [
            'base_fare' => $this->money($baseFare),
            'distance_charge' => $this->money($distanceCharge),
            'rental_charge' => $this->money($rentalCharge),
            'extra_km_charge' => $this->money($extraKmCharge),
            'extra_hour_charge' => $this->money($extraHourCharge),
            'minimum_fare_adjustment' => $this->money($minimumFareAdjustment),
            'driver_allowance' => $this->money($driverAllowance),
            'night_charge' => $this->money($nightCharge),
            'waiting_charge' => $this->money($waitingCharge),
            'toll' => $this->money($toll),
            'parking' => $this->money($parking),
            'tax' => $this->money($tax),
            'subtotal' => $this->money($subtotal),
            'discount_amount' => '0.00',
            'grand_total' => $this->money($grandTotal),
        ];

        return [
            'currency' => $currency,
            'rate_card' => $rateCard,
            'rental_package' => $rentalPackage,
            'breakdown' => $breakdown,
            'snapshot' => [
                'version' => 1,
                'currency' => $currency,
                'rate_card' => [
                    'id' => $rateCard->id,
                    'name' => $rateCard->name,
                    'vendor_profile_id' => $rateCard->vendor_profile_id,
                    'vehicle_type_id' => $rateCard->vehicle_type_id,
                    'trip_type' => $rateCard->trip_type->value,
                ],
                'rental_package' => $rentalPackage ? [
                    'id' => $rentalPackage->id,
                    'name' => $rentalPackage->name,
                    'included_hours' => $rentalPackage->included_hours,
                    'included_km' => $rentalPackage->included_km,
                    'package_price' => $rentalPackage->package_price,
                    'extra_km_rate' => $rentalPackage->extra_km_rate,
                    'extra_hour_rate' => $rentalPackage->extra_hour_rate,
                ] : null,
                'rules' => $rateCard->rules->map(fn (TaxiRateRule $rule): array => [
                    'code' => $rule->code->value,
                    'calculation_type' => $rule->calculation_type->value,
                    'amount' => $rule->amount,
                    'included_quantity' => $rule->included_quantity,
                    'unit' => $rule->unit,
                    'configuration' => $rule->configuration,
                ])->values()->all(),
                'inputs' => [
                    'distance_km' => $this->quantity($distance),
                    'duration_minutes' => $durationMinutes,
                    'waiting_minutes' => $waitingMinutes,
                    'trip_days' => $tripDays,
                    'toll_amount' => $this->money($toll),
                    'parking_amount' => $this->money($parking),
                ],
                'breakdown' => $breakdown,
                'grand_total' => $breakdown['grand_total'],
            ],
        ];
    }

    public function resolveRateCard(
        TripType $tripType,
        string $currency,
        ?int $vendorProfileId,
        ?int $vehicleTypeId,
    ): TaxiRateCard {
        $candidates = [];

        if ($vendorProfileId !== null && $vehicleTypeId !== null) {
            $candidates[] = [$vendorProfileId, $vehicleTypeId];
        }

        if ($vendorProfileId !== null) {
            $candidates[] = [$vendorProfileId, null];
        }

        if ($vehicleTypeId !== null) {
            $candidates[] = [null, $vehicleTypeId];
        }

        $candidates[] = [null, null];

        foreach ($candidates as [$candidateVendor, $candidateVehicleType]) {
            $query = TaxiRateCard::query()
                ->effective()
                ->where('trip_type', $tripType->value)
                ->where('currency', strtoupper($currency));

            $candidateVendor === null
                ? $query->whereNull('vendor_profile_id')
                : $query->where('vendor_profile_id', $candidateVendor);
            $candidateVehicleType === null
                ? $query->whereNull('vehicle_type_id')
                : $query->where('vehicle_type_id', $candidateVehicleType);

            $card = $query->orderByDesc('effective_from')->orderByDesc('id')->with(['rules', 'rentalPackages'])->first();

            if ($card !== null) {
                return $card;
            }
        }

        throw ValidationException::withMessages([
            'pricing' => 'No active taxi rate card matches this trip, currency, vendor, and vehicle type.',
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function distanceCharges(Collection $rules, string $distance, int $tripDays): array
    {
        $distanceRule = $rules->get(TaxiRateRuleCode::DistanceRate->value);

        if (! $distanceRule instanceof TaxiRateRule) {
            return ['0.000000', '0.000000'];
        }

        $config = $distanceRule->configuration ?? [];
        $includedKm = $this->decimal($distanceRule->included_quantity ?? 0);
        $minimumKm = $this->decimal($config['minimum_km'] ?? 0);

        if (($config['minimum_km_basis'] ?? 'trip') === 'day') {
            $minimumKm = bcmul($minimumKm, (string) $tripDays, self::CALCULATION_SCALE);
        }

        $billableDistance = bccomp($distance, $minimumKm, self::CALCULATION_SCALE) === -1 ? $minimumKm : $distance;
        $chargeableDistance = $this->positiveDifference($billableDistance, $includedKm);
        $extraRule = $rules->get(TaxiRateRuleCode::ExtraDistanceRate->value);

        if (! $extraRule instanceof TaxiRateRule) {
            return [
                bcmul($chargeableDistance, $this->decimal($distanceRule->amount), self::CALCULATION_SCALE),
                '0.000000',
            ];
        }

        $extraStartsAfter = $this->decimal(($extraRule->configuration ?? [])['starts_after_km'] ?? 0);

        if (bccomp($extraStartsAfter, '0', self::CALCULATION_SCALE) !== 1) {
            return ['0.000000', bcmul($chargeableDistance, $this->decimal($extraRule->amount), self::CALCULATION_SCALE)];
        }

        $regularLimit = $this->positiveDifference($extraStartsAfter, $includedKm);
        $regularDistance = bccomp($chargeableDistance, $regularLimit, self::CALCULATION_SCALE) === 1 ? $regularLimit : $chargeableDistance;
        $extraDistance = $this->positiveDifference($billableDistance, $extraStartsAfter);

        return [
            bcmul($regularDistance, $this->decimal($distanceRule->amount), self::CALCULATION_SCALE),
            bcmul($extraDistance, $this->decimal($extraRule->amount), self::CALCULATION_SCALE),
        ];
    }

    private function resolveRentalPackage(TaxiRateCard $rateCard, mixed $packageId): TaxiRentalPackage
    {
        $id = $this->nullablePositiveInteger($packageId, 'rental_package_id');

        if ($id === null) {
            throw ValidationException::withMessages(['rental_package_id' => 'A rental package is required for hourly pricing.']);
        }

        $package = $rateCard->rentalPackages->first(fn (TaxiRentalPackage $candidate): bool => $candidate->id === $id && $candidate->is_active);

        if (! $package) {
            throw ValidationException::withMessages(['rental_package_id' => 'The selected rental package is not active on the resolved rate card.']);
        }

        return $package;
    }

    private function allowance(?TaxiRateRule $rule, int $tripDays): string
    {
        if (! $rule) {
            return '0.000000';
        }

        return $rule->calculation_type === TaxiRateCalculationType::PerDay
            ? bcmul($this->decimal($rule->amount), (string) $tripDays, self::CALCULATION_SCALE)
            : $this->decimal($rule->amount);
    }

    private function nightCharge(?TaxiRateRule $rule, string $coreFare, mixed $pickupAt): string
    {
        if (! $rule || ! $this->isNight($pickupAt, $rule->configuration ?? [])) {
            return '0.000000';
        }

        if ($rule->calculation_type === TaxiRateCalculationType::Percentage) {
            return bcdiv(bcmul($coreFare, $this->decimal($rule->amount), self::CALCULATION_SCALE), '100', self::CALCULATION_SCALE);
        }

        return $this->decimal($rule->amount);
    }

    /** @param array<string, mixed> $configuration */
    private function isNight(mixed $pickupAt, array $configuration): bool
    {
        if (! is_string($pickupAt) || trim($pickupAt) === '') {
            return false;
        }

        try {
            $time = Carbon::parse($pickupAt)->format('H:i');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['pickup_at' => 'Pickup date and time is invalid for night pricing.']);
        }

        $start = (string) ($configuration['start_time'] ?? '22:00');
        $end = (string) ($configuration['end_time'] ?? '06:00');

        return $start <= $end
            ? $time >= $start && $time < $end
            : $time >= $start || $time < $end;
    }

    private function waitingCharge(?TaxiRateRule $rule, int $waitingMinutes): string
    {
        if (! $rule || $waitingMinutes === 0) {
            return '0.000000';
        }

        $increment = max(1, (int) (($rule->configuration ?? [])['billing_increment_minutes'] ?? 1));
        $billableMinutes = (int) (ceil($waitingMinutes / $increment) * $increment);

        if ($rule->calculation_type === TaxiRateCalculationType::PerHour) {
            return bcdiv(
                bcmul($this->decimal($rule->amount), (string) $billableMinutes, self::CALCULATION_SCALE),
                '60',
                self::CALCULATION_SCALE,
            );
        }

        return $this->decimal($rule->amount);
    }

    private function passThroughCharge(?TaxiRateRule $rule, mixed $actualInput, bool $authorized, string $field): string
    {
        $actual = $this->nonNegativeDecimal($actualInput, $field);

        if (! $rule || $rule->calculation_type === TaxiRateCalculationType::Included) {
            return '0.000000';
        }

        if ($rule->calculation_type === TaxiRateCalculationType::Actual) {
            if (bccomp($actual, '0', self::CALCULATION_SCALE) === 1 && ! $authorized) {
                throw ValidationException::withMessages([$field => 'Actual toll and parking amounts require an authorized operational flow.']);
            }

            return $authorized ? $actual : '0.000000';
        }

        return $this->decimal($rule->amount);
    }

    private function tax(?TaxiRateRule $rule, string $subtotal): string
    {
        if (! $rule) {
            return '0.000000';
        }

        if ($rule->calculation_type === TaxiRateCalculationType::Percentage) {
            return bcdiv(bcmul($subtotal, $this->decimal($rule->amount), self::CALCULATION_SCALE), '100', self::CALCULATION_SCALE);
        }

        return $this->decimal($rule->amount);
    }

    private function fixedRuleAmount(Collection $rules, TaxiRateRuleCode $code): string
    {
        $rule = $rules->get($code->value);

        return $rule instanceof TaxiRateRule ? $this->decimal($rule->amount) : '0.000000';
    }

    private function tripDays(TripType $tripType, mixed $pickupAt, mixed $returnAt): int
    {
        if (! in_array($tripType, [TripType::RoundTrip, TripType::Outstation], true)) {
            return 1;
        }

        if (! is_string($pickupAt) || ! is_string($returnAt) || trim($returnAt) === '') {
            throw ValidationException::withMessages(['return_at' => 'A return date and time is required for round-trip and outstation pricing.']);
        }

        try {
            $pickup = Carbon::parse($pickupAt);
            $return = Carbon::parse($returnAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['return_at' => 'The return date and time is invalid.']);
        }

        if ($return->lte($pickup)) {
            throw ValidationException::withMessages(['return_at' => 'The return date and time must be after pickup.']);
        }

        return max(1, (int) ceil($pickup->diffInMinutes($return) / 1440));
    }

    private function tripType(mixed $value): TripType
    {
        $tripType = is_string($value) ? TripType::tryFrom($value) : null;

        if (! $tripType) {
            throw ValidationException::withMessages(['trip_type' => 'A supported taxi trip type is required.']);
        }

        return $tripType;
    }

    private function nullablePositiveInteger(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw ValidationException::withMessages([$field => 'The selected value is invalid.']);
        }

        return (int) $value;
    }

    private function nonNegativeInteger(mixed $value, string $field): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw ValidationException::withMessages([$field => 'The value must be a non-negative whole number.']);
        }

        return (int) $value;
    }

    private function nonNegativeDecimal(mixed $value, string $field): string
    {
        if (! is_numeric($value) || bccomp($this->decimal($value), '0', self::CALCULATION_SCALE) === -1) {
            throw ValidationException::withMessages([$field => 'The value must be a non-negative number.']);
        }

        return $this->decimal($value);
    }

    private function decimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.000000';
        }

        $value = (string) $value;

        if (str_contains(strtolower($value), 'e')) {
            $value = number_format((float) $value, self::CALCULATION_SCALE, '.', '');
        }

        return bcadd($value, '0', self::CALCULATION_SCALE);
    }

    /** @param array<int, string> $values */
    private function sum(array $values): string
    {
        return array_reduce(
            $values,
            fn (string $carry, string $value): string => bcadd($carry, $value, self::CALCULATION_SCALE),
            '0.000000',
        );
    }

    private function positiveDifference(string $left, string $right): string
    {
        return bccomp($left, $right, self::CALCULATION_SCALE) === 1
            ? bcsub($left, $right, self::CALCULATION_SCALE)
            : '0.000000';
    }

    private function money(string $value): string
    {
        $rounded = bcadd($value, '0.005000', self::CALCULATION_SCALE);

        return bcadd($rounded, '0', 2);
    }

    private function quantity(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
