<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'requester_user_id', 'vendor_profile_id', 'category_id',
        'subject', 'priority', 'status', 'assigned_to',
        'booking_id', 'lead_id', 'quotation_id',
        'related_type', 'related_id',
        'last_reply_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function status(): TicketStatus
    {
        return TicketStatus::from($this->status);
    }

    public function priority(): TicketPriority
    {
        return TicketPriority::from($this->priority);
    }

    public function isTerminal(): bool
    {
        return $this->status()->isTerminal();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('created_at')->orderBy('id');
    }

    public function visibleMessages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')
            ->where('is_internal_note', false)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Server-side visibility scope. Requesters (customer/vendor) see
     * only their own tickets. Staff need support.view_all for everything,
     * otherwise only tickets assigned to them.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            if ($user->can('support.view_all')) {
                return $query;
            }

            return $query->where('assigned_to', $user->id);
        }

        return $query->where('requester_user_id', $user->id);
    }

    /**
     * Stale = open/awaiting and untouched for 48h+. Cheap queue metric,
     * not a scheduler.
     */
    public function scopeStale($query)
    {
        return $query
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::PendingStaff->value])
            ->where(function ($q): void {
                $q->whereNull('last_reply_at')->orWhere('last_reply_at', '<', now()->subHours(48));
            });
    }
}
