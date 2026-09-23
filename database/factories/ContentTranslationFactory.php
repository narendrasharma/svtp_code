<?php

namespace Database\Factories;

use App\Models\ContentTranslation;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentTranslation>
 */
class ContentTranslationFactory extends Factory
{
    protected $model = ContentTranslation::class;

    public function definition(): array
    {
        return [
            'translatable_type' => Destination::class,
            'translatable_id' => 1,
            'locale' => 'hi',
            'field' => 'name',
            'value' => fake()->sentence(3),
        ];
    }
}
