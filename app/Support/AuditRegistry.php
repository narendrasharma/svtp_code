<?php

namespace App\Support;

use App\Models\AccountInvitation;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\BookingReschedule;
use App\Models\Campaign;
use App\Models\CommunicationTemplate;
use App\Models\Driver;
use App\Models\DriverAvailability;
use App\Models\DriverDocument;
use App\Models\ImpersonationLog;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\NumberSeries;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\TaxiBookingStatusHistory;
use App\Models\TaxiPayment;
use App\Models\TourModerationHistory;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleType;
use App\Models\VehicleUnavailablePeriod;
use App\Models\VendorApplication;
use App\Models\VendorDocument;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayoutAccount;
use App\Models\VendorVerification;
use App\Models\VendorWithdrawalRequest;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Central audit registry (Phase 11.5D).
 *
 * ONE place maps models → audit modules and noise filters. Models stay
 * untouched (no trait, no boot methods) so Tour/booking logic cannot
 * regress. Scheduler marker columns are ignored per model so
 * background ticks never spam the trail.
 *
 * Sensitive values are masked centrally by ActivityLogger.
 */
class AuditRegistry
{
    /**
     * @return array<class-string<Model>, array{module: string, ignore: array<int, string>}>
     */
    public static function watched(): array
    {
        $reminderMarkers = ['reminder_sent_at', 'overdue_reminder_sent_at'];

        return [
            User::class => ['module' => 'users', 'ignore' => ['remember_token', 'updated_at']],
            AccountInvitation::class => ['module' => 'users', 'ignore' => ['updated_at']],
            ImpersonationLog::class => ['module' => 'system', 'ignore' => ['updated_at']],
            VendorApplication::class => ['module' => 'vendors', 'ignore' => ['updated_at']],
            VendorDocument::class => ['module' => 'vendors', 'ignore' => ['updated_at']],
            VendorVerification::class => ['module' => 'vendors', 'ignore' => ['updated_at']],
            VendorPayoutAccount::class => ['module' => 'finance', 'ignore' => ['updated_at']],
            VendorWithdrawalRequest::class => ['module' => 'finance', 'ignore' => ['updated_at']],
            VendorLedgerEntry::class => ['module' => 'finance', 'ignore' => ['updated_at']],
            TourPackage::class => ['module' => 'tours', 'ignore' => ['updated_at']],
            TourModerationHistory::class => ['module' => 'tours', 'ignore' => ['updated_at']],
            Lead::class => ['module' => 'crm', 'ignore' => ['updated_at']],
            LeadFollowUp::class => ['module' => 'crm', 'ignore' => array_merge(['updated_at'], $reminderMarkers)],
            Quotation::class => ['module' => 'crm', 'ignore' => ['updated_at', 'expiry_reminder_sent_at']],
            Booking::class => ['module' => 'bookings', 'ignore' => ['updated_at', 'last_payment_reminder_at', 'last_travel_reminder_at', 'last_vendor_travel_reminder_at']],
            BookingPayment::class => ['module' => 'finance', 'ignore' => ['updated_at']],
            BookingReschedule::class => ['module' => 'bookings', 'ignore' => ['updated_at']],
            BookingRefund::class => ['module' => 'finance', 'ignore' => ['updated_at']],
            BookingCancellationRequest::class => ['module' => 'bookings', 'ignore' => ['updated_at']],
            Setting::class => ['module' => 'system', 'ignore' => ['updated_at']],
            NumberSeries::class => ['module' => 'system', 'ignore' => ['updated_at', 'next_number', 'last_period']],
            Campaign::class => ['module' => 'communications', 'ignore' => ['updated_at']],
            CommunicationTemplate::class => ['module' => 'communications', 'ignore' => ['updated_at']],
            SupportTicket::class => ['module' => 'support', 'ignore' => ['updated_at', 'last_reply_at']],
            VehicleType::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            Vehicle::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            VehicleDocument::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            VehicleUnavailablePeriod::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            Driver::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            DriverDocument::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            DriverAvailability::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            TaxiBooking::class => ['module' => 'taxi', 'ignore' => ['updated_at', 'last_customer_reminder_at', 'last_vendor_reminder_at']],
            TaxiAssignment::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            TaxiBookingStatusHistory::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
            TaxiPayment::class => ['module' => 'taxi', 'ignore' => ['updated_at']],
        ];
    }

    public static function register(): void
    {
        foreach (static::watched() as $model => $config) {
            $model::created(function (Model $record) use ($model, $config): void {
                static::record($record, $model, $config, 'created', []);
            });

            $model::updated(function (Model $record) use ($model, $config): void {
                $changes = array_diff_key($record->getChanges(), array_flip($config['ignore']));
                unset($changes['updated_at']);

                if ($changes === []) {
                    return;
                }

                static::record($record, $model, $config, 'updated', $changes);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected static function record(Model $record, string $model, array $config, string $action, array $changes): void
    {
        try {
            $logger = app(ActivityLogger::class);
            $prefix = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($model)));
            $event = $prefix.'.'.$action;

            if ($action === 'updated' && static::isStatusChange($changes)) {
                $event = $prefix.'.status_changed';
            }

            $old = [];

            if ($action === 'updated') {
                $original = $record->getOriginal();

                foreach ($changes as $key => $value) {
                    $old[$key] = $original[$key] ?? null;
                }
            }

            $logger->log(
                $event,
                $config['module'],
                class_basename($model).' '.$action.static::describeSuffix($record),
                $record,
                $action === 'updated' ? $old : null,
                $action === 'created' ? static::createdAttributes($record, $config) : $changes,
            );
        } catch (\Throwable) {
            // Audit must never break domain writes.
        }
    }

    /** @param array<string, mixed> $changes */
    protected static function isStatusChange(array $changes): bool
    {
        foreach (['status', 'moderation_status', 'booking_status', 'payment_status'] as $key) {
            if (array_key_exists($key, $changes)) {
                return true;
            }
        }

        return false;
    }

    protected static function describeSuffix(Model $record): string
    {
        foreach (['reference', 'email', 'name', 'title', 'key', 'event'] as $attr) {
            try {
                $value = $record->getAttribute($attr);

                if (is_string($value) && $value !== '') {
                    return ': '.mb_substr($value, 0, 80);
                }
            } catch (\Throwable) {
            }
        }

        return ' #'.$record->getKey();
    }

    /** @return array<string, mixed> */
    protected static function createdAttributes(Model $record, array $config): array
    {
        $attrs = $record->getAttributes();
        unset($attrs['updated_at'], $attrs['created_at']);

        foreach ($config['ignore'] as $ignored) {
            unset($attrs[$ignored]);
        }

        return $attrs;
    }
}
