<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\VendorVerification;
use App\Services\MarketplaceAnalyticsService;
use App\Services\VendorEntitlementService;
use App\Services\VendorKycRequirementService;
use App\Services\VendorLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->vendorProfile()->first();
        $verification = null;

        if ($profile && $profile->vendor_verification_id) {
            $verification = VendorVerification::with('documents')->find($profile->vendor_verification_id);
        } else {
            $verification = VendorVerification::where('user_id', $user->id)->latest()->with('documents')->first();
        }

        $kycStatus = $verification?->status?->value ?? 'not_started';
        $kycSummary = null;
        if ($verification) {
            $service = app(VendorKycRequirementService::class);
            $kycSummary = $service->summarize($verification);
        }

        $range = app(MarketplaceAnalyticsService::class)->resolveRange($request->only(['preset', 'from', 'to']));
        $metrics = $profile ? app(MarketplaceAnalyticsService::class)->vendorSummary($profile, $range['from'], $range['to']) : null;

        // 11.5D: own-vendor operational extras. Platform commission
        // internals stay hidden — only the vendor's own earnings, payout
        // balance and open tickets. Never platform-wide numbers.
        $extras = null;

        if ($profile) {
            $balances = app(VendorLedgerService::class)->balances($profile->id);

            $extras = [
                'available_payout' => $balances['available_balance'],
                'recorded_earnings' => $balances['recorded_earnings'],
                'paid_out' => $balances['paid_out'],
                'open_support_tickets' => SupportTicket::where('vendor_profile_id', $profile->id)
                    ->whereIn('status', ['open', 'pending_staff'])
                    ->count(),
                'upcoming_travel' => Booking::where('vendor_profile_id', $profile->id)
                    ->whereIn('booking_status', ['pending', 'confirmed'])
                    ->whereDate('travel_date', '>=', today())
                    ->whereDate('travel_date', '<=', today()->addDays(7))
                    ->count(),
            ];
        }

        return Inertia::render('Vendor/Dashboard', [
            'profile' => $profile,
            'verification' => $verification ? [
                'id' => $verification->id,
                'status' => $verification->status instanceof \BackedEnum ? $verification->status->value : $verification->status,
                'verified_at' => $verification->verified_at,
            ] : null,
            'kycSummary' => $kycSummary,
            // Phase 11: own-vendor metrics + current plan usage. Never
            // platform-wide numbers.
            'metrics' => $metrics,
            'metricsRange' => $range,
            'extras' => $extras,
            'plan' => $profile?->plan ? [
                'id' => $profile->plan->id,
                'name' => $profile->plan->name,
                'slug' => $profile->plan->slug,
            ] : null,
            'usage' => $profile ? app(VendorEntitlementService::class)->usageSummary($profile) : null,
            'storefrontUrl' => $profile && $profile->slug ? route('vendors.show', $profile) : null,
        ]);
    }
}
