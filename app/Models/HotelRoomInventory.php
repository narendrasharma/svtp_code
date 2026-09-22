<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sparse per-date inventory override for a room type (12B.3).
 *
 * Normal dates have NO row — availability derives from the room type's
 * structural capacity (total_units or active units). A row exists only
 * when a manual override, blocked quantity, stop-sell or note applies.
 * No pricing lives here; rates arrive in 12B.4.
 */
class HotelRoomInventory extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'hotel_room_type_id', 'inventory_date',
        'capacity_override', 'blocked_units', 'stop_sell',
        'note', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inventory_date' => 'date:Y-m-d',
            'capacity_override' => 'integer',
            'blocked_units' => 'integer',
            'stop_sell' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'hotel_room_type_id');
    }

    /**
     * A row carrying no information is meaningless — the service deletes
     * such rows to keep storage sparse.
     */
    public function isDefault(): bool
    {
        return $this->capacity_override === null
            && (int) $this->blocked_units === 0
            && ! $this->stop_sell
            && trim((string) $this->note) === '';
    }
}
