<?php

namespace App\Models;

use App\Enums\LeadPriority;
use App\Enums\LeadStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'enquiry_id', 'source_id',
        'name', 'phone', 'email',
        'service_type', 'product_title', 'destination',
        'travel_start_date', 'travel_end_date',
        'adults', 'children', 'budget',
        'priority', 'status',
        'assigned_to', 'customer_user_id', 'converted_booking_id',
        'next_follow_up_at', 'last_contacted_at',
        'lost_reason', 'created_by', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'travel_start_date' => 'date',
            'travel_end_date' => 'date',
            'budget' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function status(): LeadStatus
    {
        return LeadStatus::from($this->status);
    }

    public function priority(): LeadPriority
    {
        return LeadPriority::from($this->priority);
    }

    public function serviceType(): ServiceType
    {
        return ServiceType::from($this->service_type);
    }

    public function isTerminal(): bool
    {
        return $this->status()->isTerminal();
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class)->orderBy('due_at');
    }

    public function pendingFollowUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class)->where('status', 'pending')->orderBy('due_at');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(LeadTimelineEntry::class)->latest();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest();
    }

    /**
     * Server-side visibility scope: staff with leads.view_all see every
     * lead; everyone else sees only leads assigned to them.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user !== null && $user->can('leads.view_all')) {
            return $query;
        }

        return $query->where('assigned_to', $user?->id);
    }
}
