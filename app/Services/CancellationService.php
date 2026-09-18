<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CancellationStatus;
use App\Events\CancellationReviewed;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancellation request workflow.
 *
 * Customers ask, admins decide. Approval moves the booking through the
 * centralized status transition — never directly. No refunds, no payment
 * changes: those arrive with the payment phase.
 *
 * Reusable by web, admin and future API/vendor clients.
 */
class CancellationService
{
    use NotifiesAdmins;

    public function __construct(protected BookingService $bookings) {}

    public function request(Booking $booking, User $user, ?string $reason = null): BookingCancellationRequest
    {
        if (! in_array($booking->booking_status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            throw ValidationException::withMessages([
                'booking' => 'Only upcoming bookings can be cancelled.',
            ]);
        }

        if ($booking->cancellationRequests()->where('status', CancellationStatus::Pending->value)->exists()) {
            throw ValidationException::withMessages([
                'booking' => 'A cancellation request is already pending for this booking.',
            ]);
        }

        $request = DB::transaction(fn (): BookingCancellationRequest => $booking->cancellationRequests()->create([
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => CancellationStatus::Pending->value,
        ]));

        $this->notifyAdmins(new AdminAlert('cancellation_requested', [
            'booking_id' => $booking->id,
            'reference' => $booking->booking_reference_id,
            'requester' => $user->name,
        ]));

        return $request;
    }

    public function approve(BookingCancellationRequest $cancellation, User $admin, ?string $note = null): BookingCancellationRequest
    {
        if (! $cancellation->isPending()) {
            throw ValidationException::withMessages([
                'cancellation' => 'Only pending requests can be reviewed.',
            ]);
        }

        $reviewed = DB::transaction(function () use ($cancellation, $admin, $note): BookingCancellationRequest {
            $this->bookings->changeStatus($cancellation->booking, BookingStatus::Cancelled, $admin, $note ?? 'Cancellation approved');
            $cancellation->update([
                'status' => CancellationStatus::Approved->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            return $cancellation;
        });

        CancellationReviewed::dispatch($reviewed, true);

        return $reviewed;
    }

    public function reject(BookingCancellationRequest $cancellation, User $admin, ?string $note = null): BookingCancellationRequest
    {
        if (! $cancellation->isPending()) {
            throw ValidationException::withMessages([
                'cancellation' => 'Only pending requests can be reviewed.',
            ]);
        }

        $cancellation->update([
            'status' => CancellationStatus::Rejected->value,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        CancellationReviewed::dispatch($cancellation->refresh(), false);

        return $cancellation;
    }
}
