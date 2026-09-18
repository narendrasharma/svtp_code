<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VendorApplicationStatus;
use App\Enums\VendorVerificationStatus;
use App\Events\KycDecided;
use App\Events\VendorApplicationDecided;
use App\Models\VendorApplication;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorApprovalService
{
    public function __construct(
        protected VendorKycRequirementService $kycService,
    ) {}

    /**
     * Approve an application transactionally.
     *
     * KYC is important but NOT a hard technical prerequisite — Admin has final
     * authority and may approve even with incomplete verification. The
     * $allowIncompleteKyc flag makes this explicit; only when false and the
     * config requires KYC do we block.
     *
     * @throws \RuntimeException
     */
    public function approve(VendorApplication $application, int $reviewerId, ?string $adminNote = null, bool $allowIncompleteKyc = true): VendorProfile
    {
        $profile = DB::transaction(function () use ($application, $reviewerId, $adminNote, $allowIncompleteKyc) {
            $application = VendorApplication::lockForUpdate()->findOrFail($application->id);

            if ($application->status !== VendorApplicationStatus::Pending
                && $application->status !== VendorApplicationStatus::ResubmissionRequested) {
                throw new \RuntimeException('Only pending applications can be approved.');
            }

            $verification = $application->verification;
            if (! $allowIncompleteKyc && $this->kycService->requireKycBeforeApproval()) {
                if (! $verification) {
                    throw new \RuntimeException('KYC verification required before approval.');
                }
                // Ensure verification is complete and verified where required
                if (! $this->kycService->isVerificationComplete($verification)) {
                    throw new \RuntimeException('Required KYC documents not verified.');
                }
                if ($verification->status !== VendorVerificationStatus::Verified) {
                    // Allow auto-promotion if all docs verified but status not yet flipped?
                    // Require explicit verified status.
                    throw new \RuntimeException('Verification must be marked verified before approval.');
                }
            }

            // Assign vendor role
            $user = $application->user;
            if ($user->role !== UserRole::Vendor->value) {
                $user->role = UserRole::Vendor->value;
                $user->save();
            }

            $application->status = VendorApplicationStatus::Approved;
            $application->reviewed_by = $reviewerId;
            $application->reviewed_at = now();
            if ($adminNote !== null) {
                $application->admin_note = $adminNote;
            }
            $application->save();

            // Create or update VendorProfile
            $profile = VendorProfile::updateOrCreate(
                ['user_id' => $application->user_id],
                [
                    'business_name' => $application->business_name,
                    'entity_type' => $application->entity_type,
                    'phone' => $application->phone,
                    'email' => $application->email,
                    'address' => $application->address,
                    'city' => $application->city,
                    'state' => $application->state,
                    'country_code' => $application->country_code,
                    'postcode' => $application->postcode,
                    'website' => $application->website,
                    'business_description' => $application->business_description,
                    'is_active' => true,
                    'approved_at' => now(),
                    'vendor_verification_id' => $verification?->id,
                    'verification_status' => $verification?->status?->value,
                ]
            );

            if ($profile->slug === null) {
                $profile->slug = Str::slug($profile->business_name ?: 'vendor').'-'.$profile->id;
                $profile->save();
            }

            // Phase 11: every approved vendor gets the default plan.
            app(VendorEntitlementService::class)->ensureDefaultPlan($profile, $reviewerId);

            return $profile;
        });

        VendorApplicationDecided::dispatch($application->refresh(), 'approved');

        return $profile;
    }

    public function reject(VendorApplication $application, int $reviewerId, string $reason, ?string $adminNote = null): void
    {
        DB::transaction(function () use ($application, $reviewerId, $reason, $adminNote) {
            $application = VendorApplication::lockForUpdate()->findOrFail($application->id);

            if ($application->status !== VendorApplicationStatus::Pending
                && $application->status !== VendorApplicationStatus::ResubmissionRequested) {
                throw new \RuntimeException('Only pending applications can be rejected.');
            }

            $application->status = VendorApplicationStatus::Rejected;
            $application->reviewed_by = $reviewerId;
            $application->reviewed_at = now();
            $application->rejection_reason = $reason;
            if ($adminNote !== null) {
                $application->admin_note = $adminNote;
            }
            $application->save();

            // Do NOT grant vendor role, do NOT create VendorProfile
            // Optionally mark verification as rejected if exists
            $verification = $application->verification;
            if ($verification) {
                $verification->status = VendorVerificationStatus::Rejected;
                $verification->rejection_reason = $reason;
                $verification->reviewed_at = now();
                $verification->reviewed_by = $reviewerId;
                $verification->save();
            }
        });

        VendorApplicationDecided::dispatch($application->refresh(), 'rejected');

        if ($verification = $application->verification()->first()) {
            KycDecided::dispatch($verification, 'rejected');
        }
    }

    public function requestResubmission(VendorApplication $application, int $reviewerId, string $reason, ?string $adminNote = null): void
    {
        DB::transaction(function () use ($application, $reviewerId, $reason, $adminNote) {
            $application = VendorApplication::lockForUpdate()->findOrFail($application->id);

            if ($application->status !== VendorApplicationStatus::Pending) {
                throw new \RuntimeException('Only pending applications can be marked for resubmission.');
            }

            $application->status = VendorApplicationStatus::ResubmissionRequested;
            $application->reviewed_by = $reviewerId;
            $application->reviewed_at = now();
            $application->rejection_reason = $reason;
            if ($adminNote !== null) {
                $application->admin_note = $adminNote;
            }
            $application->save();

            $verification = $application->verification;
            if ($verification) {
                $verification->status = VendorVerificationStatus::NeedsResubmission;
                $verification->rejection_reason = $reason;
                $verification->reviewed_at = now();
                $verification->reviewed_by = $reviewerId;
                $verification->save();
            }
        });

        VendorApplicationDecided::dispatch($application->refresh(), 'resubmission');

        if ($verification = $application->verification()->first()) {
            KycDecided::dispatch($verification, 'needs_resubmission');
        }
    }
}
