<?php

namespace App\Services;

use App\Enums\VendorDocumentStatus;
use App\Models\VendorVerification;

class VendorKycRequirementService
{
    /**
     * Resolve required document groups for a country + entity type.
     *
     * @return array<int, string|array<int,string>>
     */
    public function requirements(string $countryCode, string $entityType): array
    {
        $config = config('vendor_kyc.requirements', []);
        $country = strtoupper($countryCode);
        $entity = strtolower($entityType);

        $bucket = $config[$country] ?? $config['DEFAULT'] ?? $config['IN'] ?? [];
        $requirements = $bucket[$entity] ?? $bucket['_default'] ?? [];

        return $requirements;
    }

    public function catalog(): array
    {
        return config('vendor_kyc.document_catalog', []);
    }

    public function isDocumentTypeAllowed(string $type): bool
    {
        return array_key_exists($type, $this->catalog());
    }

    /**
     * Summarize verification progress.
     *
     * @return array{
     *   required: int,
     *   groups: array,
     *   submitted: int,
     *   verified: int,
     *   rejected: int,
     *   missing: array,
     *   is_complete: bool
     * }
     */
    public function summarize(VendorVerification $verification): array
    {
        $requirements = $this->requirements($verification->country_code, $verification->entity_type ?? 'individual');
        $documents = $verification->documents()->get();

        $verifiedTypes = $documents->where('status', VendorDocumentStatus::Verified)->pluck('document_type')->map(fn ($t) => $t instanceof \BackedEnum ? $t->value : $t)->all();
        $submittedTypes = $documents->pluck('document_type')->map(fn ($t) => $t instanceof \BackedEnum ? $t->value : $t)->all();
        $rejectedCount = $documents->where('status', VendorDocumentStatus::Rejected)->count();
        $verifiedCount = $documents->where('status', VendorDocumentStatus::Verified)->count();

        $groups = [];
        $missing = [];
        $complete = true;

        foreach ($requirements as $req) {
            if (is_string($req)) {
                $satisfied = in_array($req, $verifiedTypes, true);
                $groups[] = [
                    'type' => 'single',
                    'required' => [$req],
                    'satisfied' => $satisfied,
                ];
                if (! $satisfied) {
                    $missing[] = $req;
                    $complete = false;
                }
            } elseif (is_array($req)) {
                $satisfied = false;
                foreach ($req as $candidate) {
                    if (in_array($candidate, $verifiedTypes, true)) {
                        $satisfied = true;
                        break;
                    }
                }
                $groups[] = [
                    'type' => 'one_of',
                    'required' => $req,
                    'satisfied' => $satisfied,
                ];
                if (! $satisfied) {
                    $missing[] = $req;
                    $complete = false;
                }
            }
        }

        return [
            'required' => count($requirements),
            'groups' => $groups,
            'submitted' => count(array_unique($submittedTypes)),
            'verified' => $verifiedCount,
            'rejected' => $rejectedCount,
            'missing' => $missing,
            'is_complete' => $complete,
            'total_documents' => $documents->count(),
        ];
    }

    public function isVerificationComplete(VendorVerification $verification): bool
    {
        return $this->summarize($verification)['is_complete'];
    }

    /**
     * Whether KYC is required before approval per config.
     */
    public function requireKycBeforeApproval(): bool
    {
        return (bool) config('vendor_kyc.require_kyc_before_approval', true);
    }

    /**
     * Clean future hook for provider verification adapters.
     * Manual review is the Phase 4 implementation.
     */
    public function verificationAdapter(): ?object
    {
        return null;
    }
}
