<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Jobs\OperationalReminderJob;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Setting;
use App\Services\BookingPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manual payment collection (Phase 11.5B). Append-only: rows are never
 * edited or deleted here. Overpayments are blocked, not silently held.
 */
class BookingPaymentController extends Controller
{
    public function __construct(protected BookingPaymentService $payments) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'payment_method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $payment = $this->payments->recordPayment(
            $booking,
            (float) $validated['amount'],
            PaymentMethod::from($validated['payment_method']),
            $request->user(),
            $validated['note'] ?? null,
            $validated['external_reference'] ?? null,
        );

        if (! empty($validated['paid_at'])) {
            $payment->update(['paid_at' => $validated['paid_at']]);
        }

        $summary = $this->payments->summary($booking->refresh());

        return back()->with('flash', "Payment {$payment->reference} of ₹".number_format((float) $payment->amount, 2).' recorded. Outstanding ₹'.number_format($summary['due'], 2).'.');
    }

    /**
     * Optional payment due date (11.5D). Drives automatic reminders at
     * the configured offsets; clearing it disables automation for the
     * booking without inventing a date.
     */
    public function updateDueDate(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'payment_due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $booking->forceFill([
            'payment_due_date' => $validated['payment_due_date'] ?? null,
            'last_payment_reminder_at' => null,
        ])->save();

        return back()->with('flash', $validated['payment_due_date'] ?? null
            ? 'Payment due date set to '.$validated['payment_due_date'].'.'
            : 'Payment due date cleared; automatic reminders disabled for this booking.');
    }

    /**
     * Staff-triggered manual payment reminder (11.5D). Only when an
     * outstanding balance exists; uses the standard template path.
     */
    public function remind(Request $request, Booking $booking): RedirectResponse
    {
        $summary = $this->payments->summary($booking);

        if ($summary['due'] <= 0) {
            return back()->withErrors(['reminder' => 'This booking has no outstanding balance.']);
        }

        if (! $booking->user) {
            return back()->withErrors(['reminder' => 'This booking has no customer account to notify.']);
        }

        OperationalReminderJob::dispatch('payment_due', $booking->id);
        $booking->forceFill(['last_payment_reminder_at' => now()])->save();

        return back()->with('flash', 'Payment reminder queued for the customer.');
    }

    public function receipt(Booking $booking, BookingPayment $payment)
    {
        abort_unless((int) $payment->booking_id === (int) $booking->id, 404);

        return response()->view('booking-payments.receipt', [
            'receipt' => $this->payments->receiptData($payment),
            'site' => [
                'name' => Setting::getValue('site_name', config('app.name')),
                'phone' => Setting::getValue('primary_phone'),
                'email' => Setting::getValue('contact_email'),
                'address' => Setting::getValue('office_address'),
            ],
        ]);
    }
}
