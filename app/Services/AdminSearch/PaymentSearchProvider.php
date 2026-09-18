<?php

namespace App\Services\AdminSearch;

use App\Models\BookingPayment;
use App\Models\User;

/**
 * Payment receipt search by PAY reference, booking reference or
 * customer name. Amounts stay out of the payload — the receipt page
 * is one click away for authorized staff.
 */
class PaymentSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'payments';
    }

    public function label(): string
    {
        return 'Payments';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('bookings.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return BookingPayment::query()
            ->with('booking:id,booking_reference_id,customer_name')
            ->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('external_reference', 'like', $term)
                    ->orWhereHas('booking', fn ($b) => $b
                        ->where('booking_reference_id', 'like', $term)
                        ->orWhere('customer_name', 'like', $term));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (BookingPayment $payment): array => [
                'type' => 'payment',
                'label' => $payment->reference,
                'subtitle' => trim('Receipt · '.($payment->booking?->booking_reference_id ?? '')),
                'url' => route('admin.bookings.payments.receipt', [$payment->booking_id, $payment], absolute: false),
                'icon' => 'bi-receipt',
            ])
            ->all();
    }
}
