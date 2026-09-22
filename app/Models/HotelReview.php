<?php

namespace App\Models;

use Database\Factories\HotelReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReview extends Model
{
    /** @use HasFactory<HotelReviewFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    public const CATEGORY_RATINGS = [
        'cleanliness_rating' => 'Cleanliness',
        'location_rating' => 'Location',
        'service_rating' => 'Service',
        'comfort_rating' => 'Comfort',
        'value_rating' => 'Value',
    ];

    /** Only HotelReviewService assigns identity, moderation and reply fields. */
    protected $fillable = [
        'overall_rating', 'cleanliness_rating', 'location_rating', 'service_rating',
        'comfort_rating', 'value_rating', 'title', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer', 'cleanliness_rating' => 'integer',
            'location_rating' => 'integer', 'service_rating' => 'integer',
            'comfort_rating' => 'integer', 'value_rating' => 'integer',
            'verified_stay' => 'boolean', 'published_at' => 'datetime',
            'moderated_at' => 'datetime', 'replied_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Matches the first-name/last-initial convention used for taxi reviews,
     * with a fallback for account names containing contact details or markup.
     */
    public function customerDisplayName(): string
    {
        $name = trim((string) $this->user?->name);

        if ($name === '' || preg_match("/^[\p{L}\p{M}\s.'’\-]+$/u", $name) !== 1) {
            return 'Verified guest';
        }

        $parts = preg_split('/\s+/u', $name);

        return count($parts) > 1
            ? mb_substr($parts[0], 0, 40).' '.mb_substr(end($parts), 0, 1).'.'
            : mb_substr($parts[0], 0, 1).'.';
    }

    /** @return array<string, mixed> */
    public function publicPayload(): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customerDisplayName(),
            'overall_rating' => $this->overall_rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'verified_stay' => $this->verified_stay,
            'review_date' => $this->published_at?->toDateString(),
            'vendor_reply' => $this->status === self::STATUS_APPROVED && $this->vendor_reply !== null
                ? ['comment' => $this->vendor_reply, 'date' => $this->replied_at?->toDateString()]
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function managementPayload(bool $admin = false): array
    {
        return [
            ...$this->publicPayload(),
            'status' => $this->status,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'category_ratings' => $this->only(array_keys(self::CATEGORY_RATINGS)),
            'property' => ['id' => $this->property_id, 'name' => $this->property?->name, 'slug' => $this->property?->slug],
            ...($admin ? [
                'customer_name' => $this->user?->name ?? 'Deleted account',
                'vendor' => $this->property?->vendorProfile?->only(['id', 'business_name']),
                'rejection_reason' => $this->rejection_reason,
                'moderated_at' => $this->moderated_at?->toIso8601String(),
                'reply_text' => $this->vendor_reply,
            ] : []),
        ];
    }
}
