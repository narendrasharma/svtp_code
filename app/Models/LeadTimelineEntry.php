<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTimelineEntry extends Model
{
    use HasFactory;

    public const CREATED = 'created';

    public const ASSIGNED = 'assigned';

    public const STATUS_CHANGED = 'status_changed';

    public const FOLLOW_UP_ADDED = 'follow_up_added';

    public const FOLLOW_UP_COMPLETED = 'follow_up_completed';

    public const NOTE_ADDED = 'note_added';

    public const QUOTATION_CREATED = 'quotation_created';

    public const QUOTATION_SENT = 'quotation_sent';

    public const QUOTATION_ACCEPTED = 'quotation_accepted';

    public const QUOTATION_REJECTED = 'quotation_rejected';

    public const CONVERTED_TO_CUSTOMER = 'converted_to_customer';

    public const CONVERTED_TO_BOOKING = 'converted_to_booking';

    protected $fillable = ['lead_id', 'event', 'actor_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
