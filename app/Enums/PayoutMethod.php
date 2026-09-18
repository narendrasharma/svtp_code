<?php

namespace App\Enums;

/**
 * Vendor payout destination methods (Phase 8, India-first).
 *
 * Centralized method values so future methods plug in without touching
 * callers. Never store card numbers, CVVs, net-banking credentials or UPI
 * PINs — only bank account identifiers and UPI IDs (encrypted at rest).
 */
enum PayoutMethod: string
{
    case Bank = 'bank';
    case Upi = 'upi';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank transfer',
            self::Upi => 'UPI',
        };
    }
}
