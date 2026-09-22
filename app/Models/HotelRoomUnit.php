<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Optional physical room unit — Room 101, Villa A (12B.2).
 *
 * Operational/internal: allocation, housekeeping and maintenance seams
 * for later phases. Never required — room types work in aggregate mode
 * without any units. Internal notes must never reach public payloads.
 */
class HotelRoomUnit extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_MAINTENANCE,
        self::STATUS_OUT_OF_SERVICE,
    ];

    protected $fillable = ['room_type_id', 'unit_name', 'floor', 'status', 'notes'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }
}
