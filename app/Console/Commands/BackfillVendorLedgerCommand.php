<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\VendorLedgerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Credit ledger earnings for pre-Phase-7 paid vendor bookings.
 *
 * Idempotent: creditBookingEarning() resolves by unique reference, so
 * re-running never duplicates. Only paid, vendor-owned bookings with a
 * positive snapshotted earning qualify; admin-owned and unpaid rows are
 * skipped. Run once after deploying the ledger migration:
 *
 *     php artisan vendor-ledger:backfill
 */
#[Signature('vendor-ledger:backfill')]
#[Description('Idempotently credit ledger earnings for already-paid vendor bookings')]
class BackfillVendorLedgerCommand extends Command
{
    public function handle(VendorLedgerService $ledger): int
    {
        $credited = 0;
        $skipped = 0;

        Booking::whereNotNull('vendor_profile_id')
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('vendor_earning_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use ($ledger, &$credited, &$skipped): void {
                foreach ($bookings as $booking) {
                    $entry = $ledger->creditBookingEarning($booking);

                    if ($entry && $entry->wasRecentlyCreated) {
                        $credited++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->components->twoColumnDetail('Ledger earnings credited', (string) $credited);
        $this->components->twoColumnDetail('Already present / ineligible', (string) $skipped);

        return self::SUCCESS;
    }
}
