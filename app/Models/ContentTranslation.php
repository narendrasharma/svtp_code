<?php

namespace App\Models;

use Database\Factories\ContentTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Generic polymorphic content translation (Phase 13A).
 *
 * Stores locale-specific dynamic content (NOT UI strings — those live
 * in lang/ files). Only whitelisted fields per model may be written;
 * see App\Support\HasTranslations::translatableFields().
 */
class ContentTranslation extends Model
{
    /** @use HasFactory<ContentTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'field',
        'value',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
