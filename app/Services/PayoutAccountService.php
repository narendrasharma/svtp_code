<?php

namespace App\Services;

use App\Enums\PayoutAccountStatus;
use App\Enums\PayoutMethod;
use App\Events\PayoutAccountDecided;
use App\Models\User;
use App\Models\VendorPayoutAccount;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor payout destination management (Phase 8).
 *
 * One active account per vendor; submitting details replaces the row and
 * resets verification to pending. Secrets are encrypted at rest (model
 * casts) and never leave the server except as masked display values —
 * controllers must use toSafeArray(), never serialize the model.
 */
class PayoutAccountService
{
    /**
     * Create or replace the vendor's payout account.
     *
     * @param  array{method: string, account_holder_name: string, bank_name?: ?string, account_number?: ?string, ifsc?: ?string, upi_id?: ?string}  $data
     */
    public function save(VendorProfile $profile, array $data, ?User $actor = null): VendorPayoutAccount
    {
        $method = PayoutMethod::from($data['method']);

        $attributes = [
            'method' => $method->value,
            'account_holder_name' => trim((string) $data['account_holder_name']),
            'status' => PayoutAccountStatus::Pending->value,
            'verified_at' => null,
            'verified_by' => null,
            'rejection_reason' => null,
        ];

        if ($method === PayoutMethod::Bank) {
            $accountNumber = preg_replace('/\s+/', '', (string) ($data['account_number'] ?? ''));
            $ifsc = strtoupper(trim((string) ($data['ifsc'] ?? '')));

            if (! preg_match('/^\d{9,18}$/', $accountNumber)) {
                throw ValidationException::withMessages(['account_number' => 'Enter a valid bank account number (9–18 digits).']);
            }

            if (! preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
                throw ValidationException::withMessages(['ifsc' => 'Enter a valid 11-character IFSC.']);
            }

            $attributes += [
                'bank_name' => trim((string) ($data['bank_name'] ?? '')) ?: null,
                'account_number' => $accountNumber,
                'account_number_last4' => substr($accountNumber, -4),
                'ifsc' => $ifsc,
                'upi_id' => null,
                'upi_id_masked' => null,
            ];
        } else {
            $upiId = strtolower(trim((string) ($data['upi_id'] ?? '')));

            if (! preg_match('/^[\w.\-]{2,256}@[a-zA-Z]{2,64}$/', $upiId)) {
                throw ValidationException::withMessages(['upi_id' => 'Enter a valid UPI ID (e.g. name@upi).']);
            }

            $attributes += [
                'bank_name' => null,
                'account_number' => null,
                'account_number_last4' => null,
                'ifsc' => null,
                'upi_id' => $upiId,
                'upi_id_masked' => self::maskUpi($upiId),
            ];
        }

        return DB::transaction(function () use ($profile, $attributes): VendorPayoutAccount {
            $account = VendorPayoutAccount::updateOrCreate(
                ['vendor_profile_id' => $profile->id],
                $attributes
            );

            return $account->refresh();
        });
    }

    public function verify(VendorPayoutAccount $account, User $admin): VendorPayoutAccount
    {
        $account = DB::transaction(function () use ($account, $admin): VendorPayoutAccount {
            $account->update([
                'status' => PayoutAccountStatus::Verified->value,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'rejection_reason' => null,
            ]);

            return $account->refresh();
        });

        PayoutAccountDecided::dispatch($account, 'verified');

        return $account;
    }

    public function reject(VendorPayoutAccount $account, User $admin, string $reason): VendorPayoutAccount
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'A rejection reason is required.']);
        }

        $account = DB::transaction(function () use ($account, $admin, $reason): VendorPayoutAccount {
            $account->update([
                'status' => PayoutAccountStatus::Rejected->value,
                'verified_at' => null,
                'verified_by' => $admin->id,
                'rejection_reason' => $reason,
            ]);

            return $account->refresh();
        });

        PayoutAccountDecided::dispatch($account, 'rejected');

        return $account;
    }

    public function verifiedAccount(VendorProfile $profile): ?VendorPayoutAccount
    {
        return VendorPayoutAccount::where('vendor_profile_id', $profile->id)
            ->where('status', PayoutAccountStatus::Verified->value)
            ->first();
    }

    public static function maskUpi(string $upiId): string
    {
        [$user, $domain] = array_pad(explode('@', $upiId, 2), 2, '');

        return substr($user, 0, 2).'***@'.($domain !== '' ? $domain : 'upi');
    }
}
