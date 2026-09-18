<?php

use App\Enums\VendorDocumentType;

return [
    /*
    |--------------------------------------------------------------------------
    | KYC feature flags
    |--------------------------------------------------------------------------
    |
    | These can later be moved to a Settings-backed admin page. For now they
    | are config-driven so a CodeCanyon buyer can flip them without schema.
    |
    */
    'enabled' => env('VENDOR_KYC_ENABLED', true),

    // If true, admin cannot approve an application until required KYC is verified.
    'require_kyc_before_approval' => env('VENDOR_KYC_REQUIRE_BEFORE_APPROVAL', true),

    // Maximum file sizes and allowed mimes are enforced at upload time.
    'max_file_size_kb' => 5120, // 5MB
    'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'],

    /*
    |--------------------------------------------------------------------------
    | Document catalog
    |--------------------------------------------------------------------------
    |
    | Generic international catalog. Each entry describes a known document type
    | with UI hints. Do NOT accept arbitrary strings from the client.
    |
    */
    'document_catalog' => [
        VendorDocumentType::Pan->value => [
            'label' => 'PAN Card',
            'category' => 'identity',
            'description' => 'Permanent Account Number card.',
            'requires_number' => false,
            'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        ],
        VendorDocumentType::MaskedAadhaar->value => [
            'label' => 'Masked Aadhaar',
            'category' => 'address_proof',
            'description' => 'Masked Aadhaar where first 8 digits are hidden (e.g. XXXX XXXX 1234). Do NOT upload full Aadhaar.',
            'requires_number' => false,
            'sensitive' => true,
        ],
        VendorDocumentType::VoterId->value => [
            'label' => 'Voter ID',
            'category' => 'address_proof',
            'requires_number' => false,
        ],
        VendorDocumentType::DrivingLicence->value => [
            'label' => 'Driving Licence',
            'category' => 'address_proof',
            'requires_number' => false,
        ],
        VendorDocumentType::Passport->value => [
            'label' => 'Passport',
            'category' => 'address_proof',
            'requires_number' => false,
        ],
        VendorDocumentType::GstCertificate->value => [
            'label' => 'GST Certificate (GSTIN)',
            'category' => 'business',
            'description' => 'GST registration certificate.',
        ],
        VendorDocumentType::CertificateOfIncorporation->value => [
            'label' => 'Certificate of Incorporation (CIN)',
            'category' => 'business',
        ],
        VendorDocumentType::LlpCertificate->value => [
            'label' => 'LLP Certificate (LLPIN)',
            'category' => 'business',
        ],
        VendorDocumentType::PartnershipDeed->value => [
            'label' => 'Partnership Deed',
            'category' => 'business',
        ],
        VendorDocumentType::ShopEstablishmentCertificate->value => [
            'label' => 'Shop & Establishment Certificate',
            'category' => 'business',
        ],
        VendorDocumentType::TradeLicense->value => [
            'label' => 'Trade License',
            'category' => 'business',
        ],
        VendorDocumentType::MsmeUdyamCertificate->value => [
            'label' => 'MSME / Udyam Certificate',
            'category' => 'business',
        ],
        VendorDocumentType::OtherBusinessRegistration->value => [
            'label' => 'Other Business Registration',
            'category' => 'business',
        ],
        VendorDocumentType::CancelledCheque->value => [
            'label' => 'Cancelled Cheque',
            'category' => 'bank',
        ],
        VendorDocumentType::BankStatement->value => [
            'label' => 'Bank Statement',
            'category' => 'bank',
        ],
        VendorDocumentType::IataCertificate->value => [
            'label' => 'IATA Certificate',
            'category' => 'industry',
        ],
        VendorDocumentType::IrctcCertificate->value => [
            'label' => 'IRCTC Authorization',
            'category' => 'industry',
        ],
        VendorDocumentType::TourismLicence->value => [
            'label' => 'Tourism / Travel Licence',
            'category' => 'industry',
        ],
        VendorDocumentType::OtherIndustryCertificate->value => [
            'label' => 'Other Industry Certificate',
            'category' => 'industry',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Requirements matrix
    |--------------------------------------------------------------------------
    |
    | Keyed by ISO country code, then entity_type. Falls back to DEFAULT and
    | then to IN. Each requirement entry is either:
    |   - string: that document type is required
    |   - array:  one-of group — at least one type in the array is required
    |
    | This keeps configuration simple while allowing "choose one of" logic.
    |
    */
    'requirements' => [
        'IN' => [
            // Fallback for unspecified entity types
            '_default' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'individual' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'sole_proprietor' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                [VendorDocumentType::GstCertificate->value, VendorDocumentType::ShopEstablishmentCertificate->value, VendorDocumentType::OtherBusinessRegistration->value],
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'partnership' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                VendorDocumentType::PartnershipDeed->value,
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'llp' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                VendorDocumentType::LlpCertificate->value,
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'private_limited' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                VendorDocumentType::CertificateOfIncorporation->value,
                VendorDocumentType::GstCertificate->value,
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'public_limited' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                VendorDocumentType::CertificateOfIncorporation->value,
                VendorDocumentType::GstCertificate->value,
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'other' => [
                VendorDocumentType::Pan->value,
                [VendorDocumentType::MaskedAadhaar->value, VendorDocumentType::VoterId->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::Passport->value],
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
        ],
        'DEFAULT' => [
            '_default' => [
                [VendorDocumentType::Passport->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::VoterId->value],
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'individual' => [
                [VendorDocumentType::Passport->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::VoterId->value],
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
            'other' => [
                [VendorDocumentType::Passport->value, VendorDocumentType::DrivingLicence->value, VendorDocumentType::VoterId->value],
                [VendorDocumentType::CancelledCheque->value, VendorDocumentType::BankStatement->value],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'disk' => env('VENDOR_KYC_DISK', 'vendor_kyc'),
    'storage_path_prefix' => 'kyc',

    /*
    |--------------------------------------------------------------------------
    | Consent
    |--------------------------------------------------------------------------
    */
    'consent_policy_version' => 'v1',
    'consent_text' => 'I confirm that I am authorised to submit these documents for vendor verification and that they will be used solely for verification purposes.',
];
