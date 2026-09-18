<?php

namespace Database\Factories;

use App\Models\VendorPlan;
use App\Models\VendorPlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorPlan>
 */
class VendorPlanFactory extends Factory
{
    protected $model = VendorPlan::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true)).' Plan';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'description' => null,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (VendorPlan $plan): void {
            $defaults = [
                [VendorPlan::KEY_MAX_ACTIVE_TOURS, 'integer', 10, null, null],
                [VendorPlan::KEY_MAX_COUPONS, 'integer', 5, null, null],
                [VendorPlan::KEY_MAX_ADDONS_PER_TOUR, 'integer', 5, null, null],
                [VendorPlan::KEY_FEATURED_LISTING, 'boolean', null, false, null],
                [VendorPlan::KEY_STOREFRONT_ENABLED, 'boolean', null, true, null],
                [VendorPlan::KEY_ANALYTICS_LEVEL, 'string', null, null, 'basic'],
            ];

            foreach ($defaults as [$key, $type, $int, $bool, $str]) {
                VendorPlanFeature::firstOrCreate(
                    ['vendor_plan_id' => $plan->id, 'key' => $key],
                    [
                        'label' => $key,
                        'value_type' => $type,
                        'integer_value' => $int,
                        'boolean_value' => $bool,
                        'string_value' => $str,
                    ]
                );
            }

            $plan->load('features');
        });
    }
}
