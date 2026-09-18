<?php

namespace App\Enums;

/**
 * Campaign lifecycle. Without a scheduler only draft → sending (Send
 * Now) → completed/cancelled/failed is operational; scheduled is stored
 * intent for the 11.5D worker — never faked as executed.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Sending => 'Sending',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }
}
