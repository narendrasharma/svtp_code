<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuItem> */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return ['menu_id' => Menu::factory(), 'title' => fake()->words(2, true), 'type' => 'custom', 'url' => '/contact', 'parent_id' => null, 'sort_order' => 0, 'is_active' => true, 'target' => '_self'];
    }
}
