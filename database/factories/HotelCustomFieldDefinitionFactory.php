<?php

namespace Database\Factories;

use App\Models\HotelCustomFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelCustomFieldDefinition>
 */
class HotelCustomFieldDefinitionFactory extends Factory
{
    protected $model = HotelCustomFieldDefinition::class;

    public function definition(): array
    {
        // Sequence-free unique suffix: 6-digit pool shared with the other
        // hotel factories, never a tiny fixed list.
        $suffix = fake()->unique()->numerify('######');

        return [
            'entity_type' => HotelCustomFieldDefinition::ENTITY_PROPERTY,
            'name' => 'Extra Detail '.$suffix,
            'key' => 'extra_detail_'.$suffix,
            'field_type' => HotelCustomFieldDefinition::TYPE_TEXT,
            'group_name' => 'Additional Information',
            'is_required' => false,
            'is_active' => true,
            'show_on_frontend' => true,
            'show_label' => true,
            'sort_order' => 0,
            'options' => null,
        ];
    }
}
