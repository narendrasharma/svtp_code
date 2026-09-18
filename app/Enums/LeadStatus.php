<?php

namespace App\Enums;

/**
 * CRM lead pipeline. Centralized — never raw status strings in
 * controllers. Terminal states: won, lost.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case QuotationSent = 'quotation_sent';
    case FollowUp = 'follow_up';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::QuotationSent => 'Quotation sent',
            self::FollowUp => 'Follow-up',
            self::Negotiation => 'Negotiation',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
