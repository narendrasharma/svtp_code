<?php

namespace App\Enums;

/**
 * Catalog of verifier-known document types.
 *
 * Validation must only accept these values — never arbitrary request strings.
 */
enum VendorDocumentType: string
{
    // Identity / address
    case Pan = 'pan';
    case MaskedAadhaar = 'masked_aadhaar';
    case VoterId = 'voter_id';
    case DrivingLicence = 'driving_licence';
    case Passport = 'passport';

    // Business / KYB
    case GstCertificate = 'gst_certificate';
    case CertificateOfIncorporation = 'certificate_of_incorporation';
    case LlpCertificate = 'llp_certificate';
    case PartnershipDeed = 'partnership_deed';
    case ShopEstablishmentCertificate = 'shop_establishment_certificate';
    case TradeLicense = 'trade_license';
    case MsmeUdyamCertificate = 'msme_udyam_certificate';
    case OtherBusinessRegistration = 'other_business_registration';

    // Bank proof
    case CancelledCheque = 'cancelled_cheque';
    case BankStatement = 'bank_statement';

    // Travel industry (optional)
    case IataCertificate = 'iata_certificate';
    case IrctcCertificate = 'irctc_certificate';
    case TourismLicence = 'tourism_licence';
    case OtherIndustryCertificate = 'other_industry_certificate';

    public function label(): string
    {
        return match ($this) {
            self::Pan => 'PAN Card',
            self::MaskedAadhaar => 'Masked Aadhaar',
            self::VoterId => 'Voter ID',
            self::DrivingLicence => 'Driving Licence',
            self::Passport => 'Passport',
            self::GstCertificate => 'GST Certificate (GSTIN)',
            self::CertificateOfIncorporation => 'Certificate of Incorporation (CIN)',
            self::LlpCertificate => 'LLP Certificate (LLPIN)',
            self::PartnershipDeed => 'Partnership Deed',
            self::ShopEstablishmentCertificate => 'Shop & Establishment Certificate',
            self::TradeLicense => 'Trade License',
            self::MsmeUdyamCertificate => 'MSME / Udyam Certificate',
            self::OtherBusinessRegistration => 'Other Business Registration',
            self::CancelledCheque => 'Cancelled Cheque',
            self::BankStatement => 'Bank Statement',
            self::IataCertificate => 'IATA Certificate',
            self::IrctcCertificate => 'IRCTC Authorization',
            self::TourismLicence => 'Tourism / Travel Licence',
            self::OtherIndustryCertificate => 'Other Industry Certificate',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Pan => 'identity',
            self::MaskedAadhaar, self::VoterId, self::DrivingLicence, self::Passport => 'address_proof',
            self::GstCertificate, self::CertificateOfIncorporation, self::LlpCertificate, self::PartnershipDeed,
            self::ShopEstablishmentCertificate, self::TradeLicense, self::MsmeUdyamCertificate, self::OtherBusinessRegistration => 'business',
            self::CancelledCheque, self::BankStatement => 'bank',
            self::IataCertificate, self::IrctcCertificate, self::TourismLicence, self::OtherIndustryCertificate => 'industry',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'category' => $c->category(),
        ], self::cases());
    }
}
