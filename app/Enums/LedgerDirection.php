<?php

namespace App\Enums;

/**
 * Vendor ledger movement direction.
 *
 * Amounts are always stored positive; the direction gives the sign.
 * Credits increase the vendor's recorded position, debits decrease it.
 */
enum LedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Credit',
            self::Debit => 'Debit',
        };
    }
}
