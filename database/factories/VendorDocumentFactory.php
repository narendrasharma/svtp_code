<?php

namespace Database\Factories;

use App\Enums\VendorDocumentStatus;
use App\Enums\VendorDocumentType;
use App\Models\VendorDocument;
use App\Models\VendorVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorDocument>
 */
class VendorDocumentFactory extends Factory
{
    protected $model = VendorDocument::class;

    public function definition(): array
    {
        return [
            'vendor_verification_id' => VendorVerification::factory(),
            'document_type' => fake()->randomElement(VendorDocumentType::cases())->value,
            'document_number_masked' => null,
            'original_filename' => fake()->word().'.pdf',
            'storage_path' => 'kyc/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'status' => VendorDocumentStatus::Pending->value,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorDocumentStatus::Verified->value, 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $a) => ['status' => VendorDocumentStatus::Rejected->value, 'rejection_reason' => 'Invalid document']);
    }

    public function pan(): static
    {
        return $this->state(fn (array $a) => ['document_type' => VendorDocumentType::Pan->value]);
    }

    public function maskedAadhaar(): static
    {
        return $this->state(fn (array $a) => ['document_type' => VendorDocumentType::MaskedAadhaar->value, 'document_number_masked' => 'XXXX-XXXX-1234']);
    }
}
