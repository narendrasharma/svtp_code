<?php

namespace Database\Factories;

use App\Models\HotelBedType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelBedType>
 */
class HotelBedTypeFactory extends Factory
{
    protected $model = HotelBedType::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');

        return [
            'name' => 'Cloud Bed '.$suffix,
            'slug' => 'cloud-bed-'.$suffix,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
