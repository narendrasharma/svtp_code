<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\VendorLedgerEntry;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorLedgerEntry>
 */
class VendorLedgerEntryFactory extends Factory
{
    protected $model = VendorLedgerEntry::class;

    public function definition(): array
    {
        return [
            'vendor_profile_id' => VendorProfile::factory(),
            'booking_id' => null,
            'withdrawal_request_id' => null,
            'type' => LedgerEntryType::BookingEarning->value,
            'direction' => LedgerDirection::Credit->value,
            'amount' => '9000.00',
            'currency' => 'INR',
            'reference' => 'test-'.Str::uuid()->toString(),
            'description' => 'Test ledger entry',
            'metadata' => null,
            'created_by' => null,
        ];
    }
}
