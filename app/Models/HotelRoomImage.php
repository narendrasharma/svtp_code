<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Room type gallery image (12B.2). Mirrors the property image pattern:
 * files on the public disk under `rooms/{roomTypeId}/`.
 */
class HotelRoomImage extends Model
{
    protected $fillable = ['room_type_id', 'path', 'alt_text', 'sort_order', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
