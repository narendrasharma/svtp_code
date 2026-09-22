<?php

namespace Database\Factories;

use App\Models\HotelAmenity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelAmenity>
 */
class HotelAmenityFactory extends Factory
{
    protected $model = HotelAmenity::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');

        return [
            'name' => 'Amenity '.$suffix,
            'slug' => 'amenity-'.$suffix,
            'category' => 'General',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
