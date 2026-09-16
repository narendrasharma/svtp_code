<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'title',
        'type',          // 'page' or 'custom'
        'page_id',       // nullable, when type = page
        'url',           // nullable, when type = custom
        'sort_order',
        'is_active',
        'target',        // '_self' or '_blank'
    ];

    protected $casts = ['is_active' => 'boolean', 'parent_id' => 'integer', 'sort_order' => 'integer'];

    /**
     * The menu this item belongs to.
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Parent menu item (for nesting).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child menu items.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * If the item links to a page.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
