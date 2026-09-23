<?php

namespace Database\Factories;

use App\Models\HomepageSectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSectionItem>
 */
class HomepageSectionItemFactory extends Factory
{
    protected $model = HomepageSectionItem::class;

    public function definition(): array
    {
        return [
            'homepage_section_id' => HomepageSectionFactory::new(),
            'entity_type' => 'destination',
            'entity_id' => 1,
            'sort_order' => 0,
        ];
    }
}
