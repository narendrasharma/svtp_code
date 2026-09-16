<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(4);
        $title = Str::headline(str_replace('-', ' ', $slug));
        $metaDescription = Str::limit(fake()->sentences(3, true), 160, '');

        return [
            'title' => $title,
            'slug' => $slug,
            'template' => fake()->randomElement(Page::availableTemplates()),
            'excerpt' => fake()->optional()->sentences(2, true),
            'content' => collect(range(1, 3))
                ->map(fn () => '<p>'.fake()->paragraph().'</p>')
                ->implode("\n\n"),
            'is_active' => fake()->boolean(85),
            'sort_order' => fake()->numberBetween(0, 999),
            'meta_title' => Str::limit($title.' | '.config('app.name'), 70, ''),
            'meta_description' => $metaDescription,
        ];
    }
}
