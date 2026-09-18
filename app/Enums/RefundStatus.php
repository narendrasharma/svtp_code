<?php

namespace App\Enums;

/**
 * Manual accounting refund states (Phase 8).
 *
 * No gateway integration yet: admin records/processes directly. Failed and
 * cancelled rows never move ledger money — only processed rows do.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processed => 'Processed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }
}
