<?php

namespace App\Models;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Page
 *
 * Represents a CMS page.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $template
 * @property string|null $excerpt
 * @property string|null $content
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $meta_title
 * @property string|null $meta_description
 *
 * @method static Builder|static active()
 * @method static Builder|static ordered()
 */
class Page extends Model
{
    use HasFactory;
    use HasTranslations;

    /**
     * -----------------------------------------------------------------
     * Fillable attributes – these are the columns that can be mass‑
     * assigned via `create()` or `update()`.
     * -----------------------------------------------------------------
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'template',
        'excerpt',
        'is_active',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    /**
     * -----------------------------------------------------------------
     * Default attribute values – ensures a newly instantiated model has
     * sensible defaults even before it is persisted.
     * -----------------------------------------------------------------
     */
    protected $attributes = [
        'template' => self::TEMPLATE_DEFAULT,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * -----------------------------------------------------------------
     * Casts – convert database values to proper PHP types.
     * -----------------------------------------------------------------
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * -----------------------------------------------------------------
     * Template constants – define the set of allowed templates.
     * -----------------------------------------------------------------
     */
    public const TEMPLATE_DEFAULT = 'default';

    /**
     * Phase 13A representative translatable domain (shared platform).
     *
     * @return array<int, string>
     */
    public static function translatableFields(): array
    {
        return ['title', 'excerpt', 'content', 'meta_title', 'meta_description'];
    }

    /**
     * Return all available template identifiers.
     *
     * @return array<int,string>
     */
    public static function availableTemplates(): array
    {
        return [
            self::TEMPLATE_DEFAULT,
        ];
    }

    /**
     * -----------------------------------------------------------------
     * Scopes
     * -----------------------------------------------------------------
     */

    /**
     * Only active pages.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Order pages by `sort_order` (ascending) then by `title`.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * -----------------------------------------------------------------
     * Relationships
     * -----------------------------------------------------------------
     */

    /**
     * Menu items that link to this page.
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'page_id');
    }
}
