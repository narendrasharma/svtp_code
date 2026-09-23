<?php

namespace App\Models;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageSection extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'section_key',
        'section_type',
        'is_active',
        'sort_order',
        'source_mode',
        'item_limit',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'item_limit' => 'integer',
        'settings' => 'array',
    ];

    /**
     * @return array<int, string>
     */
    public static function translatableFields(): array
    {
        return ['title', 'subtitle', 'cta_label'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomepageSectionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getTitleAttribute(): ?string
    {
        return $this->settings['title'] ?? null;
    }

    public function getSubtitleAttribute(): ?string
    {
        return $this->settings['subtitle'] ?? null;
    }

    public function getCtaLabelAttribute(): ?string
    {
        return $this->settings['cta_label'] ?? null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeMerchandising($query)
    {
        return $query->whereNotNull('section_type');
    }
}
