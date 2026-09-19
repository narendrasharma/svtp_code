<?php

namespace App\Services;

use App\Models\NumberSeries;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Central human-readable reference generator (Phase 11.5A).
 *
 * DB primary keys stay internal; every customer-visible reference
 * (bookings, invoices, refunds, withdrawals, ...) is issued here from a
 * per-entity series row so concurrent requests can never collide:
 * the series row is locked (SELECT ... FOR UPDATE) inside a transaction
 * and the entity tables keep their UNIQUE constraints as final guard.
 *
 * Format (controlled placeholders only, no executable templates):
 *   {PREFIX}{SEP}{YYYY}{SEP}{MM}{SEP}{SEQ padded}
 * e.g. BK-2026-000001, CUS-000001, INV-2026-000123.
 */
class NumberSeriesService
{
    /**
     * Canonical series definitions. Still-future entities (taxi ride,
     * hotel reservation) ship as definitions only — no entity tables
     * are created in this phase.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'booking' => ['display_name' => 'Booking', 'prefix' => 'BK', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Tour booking references (booking_reference_id).'],
            'customer' => ['display_name' => 'Customer', 'prefix' => 'CUS', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'Customer account references (future use).'],
            'vendor' => ['display_name' => 'Vendor', 'prefix' => 'VEN', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'Vendor account references (future use).'],
            'staff' => ['display_name' => 'Staff', 'prefix' => 'STF', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 4, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'Internal staff references (future use).'],
            'invoice' => ['display_name' => 'Invoice', 'prefix' => 'INV', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Invoice numbers.'],
            'refund' => ['display_name' => 'Refund', 'prefix' => 'RF', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Manual accounting refund references.'],
            'withdrawal' => ['display_name' => 'Withdrawal', 'prefix' => 'WD', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Vendor withdrawal request references.'],
            'lead' => ['display_name' => 'Lead', 'prefix' => 'LEAD', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'CRM lead references.'],
            'quotation' => ['display_name' => 'Quotation', 'prefix' => 'QT', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Quotation references (shared across revisions of one quotation).'],
            'payment' => ['display_name' => 'Payment', 'prefix' => 'PAY', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Booking payment receipt references.'],
            'support_ticket' => ['display_name' => 'Support Ticket', 'prefix' => 'SUP', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'Support ticket references.'],
            'campaign' => ['display_name' => 'Campaign', 'prefix' => 'CMP', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'Newsletter campaign references.'],
            'taxi_refund' => ['display_name' => 'Taxi Refund', 'prefix' => 'TXR', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Manual taxi refund references.'],
            'taxi_ride' => ['display_name' => 'Taxi Ride', 'prefix' => 'TX', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Taxi booking references.'],
            'taxi_driver' => ['display_name' => 'Taxi Driver', 'prefix' => 'DRV', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'Taxi driver references.'],
            'taxi_vehicle' => ['display_name' => 'Taxi Vehicle', 'prefix' => 'VEH', 'separator' => '-', 'include_year' => false, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_NEVER, 'description' => 'Taxi vehicle references.'],
            'taxi_driver_earning' => ['display_name' => 'Driver Earning', 'prefix' => 'DRE', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Taxi driver earning references.'],
            'taxi_driver_payout' => ['display_name' => 'Driver Payout', 'prefix' => 'DRP', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Taxi driver payout batch references.'],
            'hotel_reservation' => ['display_name' => 'Hotel Reservation (future)', 'prefix' => 'HT', 'separator' => '-', 'include_year' => true, 'include_month' => false, 'padding' => 6, 'start_number' => 1, 'reset_cycle' => NumberSeries::RESET_YEARLY, 'description' => 'Hotels module not installed; definition only.'],
        ];
    }

    /**
     * Issue the next reference for an entity. Transaction-safe: the
     * series row is locked for update so simultaneous callers serialize.
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function next(string $entity, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();

        return DB::transaction(function () use ($entity, $now): string {
            /** @var NumberSeries|null $series */
            $series = NumberSeries::where('entity', $entity)->lockForUpdate()->first();

            if (! $series || ! $series->is_active) {
                throw new InvalidArgumentException("Number series [{$entity}] is not configured.");
            }

            $period = $this->periodKey($series->reset_cycle, $now);

            $nextNumber = (int) $series->next_number;
            if ($series->reset_cycle !== NumberSeries::RESET_NEVER && $series->last_period !== $period) {
                $nextNumber = (int) $series->start_number;
            }

            if ($nextNumber < 1) {
                $nextNumber = 1;
            }

            $reference = $this->format($series, $nextNumber, $now);

            $series->next_number = $nextNumber + 1;
            $series->last_period = $period;
            $series->save();

            return $reference;
        });
    }

    /**
     * Preview what the next reference would look like (no increment).
     */
    public function preview(NumberSeries $series, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $nextNumber = (int) $series->next_number;

        if ($series->reset_cycle !== NumberSeries::RESET_NEVER && $series->last_period !== $this->periodKey($series->reset_cycle, $now)) {
            $nextNumber = (int) $series->start_number;
        }

        return $this->format($series, max($nextNumber, 1), $now);
    }

    public function format(NumberSeries $series, int $sequence, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $separator = $series->separator ?? '-';

        $parts = [$series->prefix];

        if ($series->include_year) {
            $parts[] = $now->format('Y');
        }

        if ($series->include_month) {
            $parts[] = $now->format('m');
        }

        $parts[] = str_pad((string) $sequence, max((int) $series->padding, 1), '0', STR_PAD_LEFT);

        return implode($separator, $parts);
    }

    public function periodKey(string $resetCycle, Carbon $now): ?string
    {
        return match ($resetCycle) {
            NumberSeries::RESET_YEARLY => $now->format('Y'),
            NumberSeries::RESET_MONTHLY => $now->format('Y-m'),
            default => null,
        };
    }

    /**
     * Validate admin-supplied series configuration. Invalid
     * configurations are rejected before anything is persisted.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function validateConfiguration(array $input): array
    {
        $prefix = strtoupper(trim((string) ($input['prefix'] ?? '')));
        $separator = (string) ($input['separator'] ?? '-');
        $padding = (int) ($input['padding'] ?? 6);
        $startNumber = (int) ($input['start_number'] ?? 1);
        $nextNumber = (int) ($input['next_number'] ?? $startNumber);
        $resetCycle = (string) ($input['reset_cycle'] ?? NumberSeries::RESET_NEVER);

        if (! preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
            throw new InvalidArgumentException('Prefix must be 1-10 uppercase letters/digits.');
        }

        if (! in_array($separator, ['-', '/', '_', ''], true)) {
            throw new InvalidArgumentException('Separator must be one of: - / _ or empty.');
        }

        if ($padding < 3 || $padding > 10) {
            throw new InvalidArgumentException('Padding must be between 3 and 10 digits.');
        }

        if ($startNumber < 1 || $startNumber > 999999999) {
            throw new InvalidArgumentException('Starting number must be between 1 and 999999999.');
        }

        if ($nextNumber < 1 || $nextNumber > 999999999) {
            throw new InvalidArgumentException('Next number must be between 1 and 999999999.');
        }

        if (! in_array($resetCycle, NumberSeries::resetCycles(), true)) {
            throw new InvalidArgumentException('Invalid reset cycle.');
        }

        return [
            'prefix' => $prefix,
            'separator' => $separator,
            'include_year' => (bool) ($input['include_year'] ?? false),
            'include_month' => (bool) ($input['include_month'] ?? false),
            'padding' => $padding,
            'start_number' => $startNumber,
            'next_number' => $nextNumber,
            'reset_cycle' => $resetCycle,
        ];
    }
}
