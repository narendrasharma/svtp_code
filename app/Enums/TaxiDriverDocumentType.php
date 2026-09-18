<?php

namespace App\Enums;

enum TaxiDriverDocumentType: string
{
    case DrivingLicense = 'driving_license';
    case IdentityProof = 'identity_proof';
    case PoliceVerification = 'police_verification';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DrivingLicense => 'Driving Licence',
            self::IdentityProof => 'Identity Proof',
            self::PoliceVerification => 'Police Verification',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
