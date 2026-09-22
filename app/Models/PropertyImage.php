<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Property gallery image (12B.1). Files live on the public disk under
 * `properties/{id}/`; deleting the row must delete the file (handled
 * by HotelPropertyService, mirroring the banner pattern).
 */
class PropertyImage extends Model
{
    protected $fillable = ['property_id', 'path', 'alt_text', 'sort_order', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
