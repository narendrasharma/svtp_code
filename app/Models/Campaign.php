<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\CommunicationChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    public const AUDIENCE_ALL_CUSTOMERS = 'all_customers';

    public const AUDIENCE_ALL_VENDORS = 'all_vendors';

    public const AUDIENCE_ALL_USERS = 'all_users';

    public const AUDIENCE_SELECTED = 'selected';

    protected $fillable = [
        'reference', 'name', 'subject', 'content',
        'channel', 'status', 'audience_type', 'audience_filter',
        'scheduled_at', 'sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience_filter' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function status(): CampaignStatus
    {
        return CampaignStatus::from($this->status);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class);
    }

    /**
     * @return array<int, string>
     */
    public static function audienceTypes(): array
    {
        return [
            self::AUDIENCE_ALL_CUSTOMERS,
            self::AUDIENCE_ALL_VENDORS,
            self::AUDIENCE_ALL_USERS,
            self::AUDIENCE_SELECTED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function sendableChannels(): array
    {
        // Email + in-app deliver through first-party code today.
        // sms/whatsapp join once a provider is configured (11.5D).
        return [CommunicationChannel::Email->value, CommunicationChannel::InApp->value];
    }
}
