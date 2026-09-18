<?php

namespace App\Enums;

/**
 * Quotation lifecycle. A quotation is a commercial offer — never an
 * invoice. Terminal states: accepted, rejected, expired, superseded,
 * converted. Revisions share one reference; only the newest revision is
 * ever current (older ones become superseded).
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Viewed => 'Viewed',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Superseded => 'Superseded',
            self::Converted => 'Converted',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Expired, self::Superseded, self::Converted], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Sent, self::Viewed], true);
    }
}
