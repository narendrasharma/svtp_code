<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorPlan;
use App\Services\ImpersonationService;
use App\Services\VendorEntitlementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $role = $request->string('role')->toString();
        $sort = $request->string('sort')->toString() ?: 'newest';
        $perPage = (int) ($request->integer('per_page') ?: 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        $query = User::query()
            ->with(['vendorProfile', 'roles:id,name', 'vendorApplications' => fn ($q) => $q->latest()->limit(1), 'vendorVerifications' => fn ($q) => $q->latest()->limit(1)])
            ->withCount('bookings');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($role !== '' && in_array($role, array_column(UserRole::cases(), 'value'), true)) {
            $query->where('role', $role);
        }

        // Sorting
        match ($sort) {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'email_asc' => $query->orderBy('email', 'asc'),
            'email_desc' => $query->orderBy('email', 'desc'),
            'role_asc' => $query->orderBy('role', 'asc'),
            'role_desc' => $query->orderBy('role', 'desc'),
            default => $query->latest(),
        };

        $users = $query->paginate($perPage)->withQueryString();

        // Transform to avoid exposing sensitive fields
        $users->getCollection()->transform(function (User $user) {
            $latestApp = $user->vendorApplications->first();
            $latestVer = $user->vendorVerifications->first();
            $vendorProfile = $user->vendorProfile;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'role_label' => UserRole::tryFrom($user->role)?->label() ?? $user->role,
                'created_at' => $user->created_at,
                'bookings_count' => $user->bookings_count,
                'vendor_business_name' => $vendorProfile?->business_name,
                'vendor_status' => $vendorProfile ? ($vendorProfile->is_active ? 'active' : 'inactive') : null,
                'application_status' => $latestApp?->status?->value ?? $latestApp?->status,
                'kyc_status' => $latestVer?->status?->value ?? $latestVer?->status,
                'can_impersonate' => $this->canImpersonate(auth()->user(), $user),
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'sort', 'per_page']),
            'roles' => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
            'sortOptions' => [
                ['value' => 'newest', 'label' => 'Newest'],
                ['value' => 'oldest', 'label' => 'Oldest'],
                ['value' => 'name_asc', 'label' => 'Name A-Z'],
                ['value' => 'name_desc', 'label' => 'Name Z-A'],
                ['value' => 'email_asc', 'label' => 'Email A-Z'],
                ['value' => 'email_desc', 'label' => 'Email Z-A'],
                ['value' => 'role_asc', 'label' => 'Role A-Z'],
            ],
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    public function show(User $user): Response
    {
        $user->load(['vendorProfile', 'vendorApplications' => fn ($q) => $q->latest(), 'vendorVerifications' => fn ($q) => $q->latest()->with('documents'), 'bookings' => fn ($q) => $q->latest()->limit(5)->with('package:id,title')]);
        $user->loadCount('bookings');

        // Recent bookings (already loaded 5)
        $recentBookings = $user->bookings->map(fn ($b) => [
            'id' => $b->id,
            'booking_reference_id' => $b->booking_reference_id,
            'package' => $b->package,
            'travel_date' => $b->travel_date,
            'booking_status' => $b->booking_status instanceof \BackedEnum ? $b->booking_status->value : $b->booking_status,
            'created_at' => $b->created_at,
        ]);

        $latestApp = $user->vendorApplications->first();
        $vendorProfile = $user->vendorProfile;
        $latestVer = $user->vendorVerifications->first();

        // Safe user data (never expose password etc)
        $safeUser = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'role_label' => UserRole::tryFrom($user->role)?->label() ?? $user->role,
            'created_at' => $user->created_at,
            'email_verified_at' => $user->email_verified_at,
            'bookings_count' => $user->bookings_count,
            'can_impersonate' => $this->canImpersonate(auth()->user(), $user),
        ];

        return Inertia::render('Admin/Users/Show', [
            'user' => $safeUser,
            'recentBookings' => $recentBookings,
            'vendorProfile' => $vendorProfile ? [
                'id' => $vendorProfile->id,
                'business_name' => $vendorProfile->business_name,
                'slug' => $vendorProfile->slug,
                'entity_type' => $vendorProfile->entity_type,
                'phone' => $vendorProfile->phone,
                'email' => $vendorProfile->email,
                'city' => $vendorProfile->city,
                'state' => $vendorProfile->state,
                'country_code' => $vendorProfile->country_code,
                'is_active' => $vendorProfile->is_active,
                'approved_at' => $vendorProfile->approved_at,
                'verification_status' => $vendorProfile->verification_status,
                'vendor_plan_id' => $vendorProfile->vendor_plan_id,
            ] : null,
            // Phase 11: current plan + usage for manual assignment UX.
            'vendorPlan' => $vendorProfile?->plan ? [
                'id' => $vendorProfile->plan->id,
                'name' => $vendorProfile->plan->name,
                'slug' => $vendorProfile->plan->slug,
            ] : null,
            'vendorUsage' => $vendorProfile ? app(VendorEntitlementService::class)->usageSummary($vendorProfile) : null,
            'vendorPlans' => $vendorProfile ? VendorPlan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']) : [],
            'vendorApplication' => $latestApp ? [
                'id' => $latestApp->id,
                'business_name' => $latestApp->business_name,
                'entity_type' => $latestApp->entity_type,
                'status' => $latestApp->status instanceof \BackedEnum ? $latestApp->status->value : $latestApp->status,
                'city' => $latestApp->city,
                'country_code' => $latestApp->country_code,
                'created_at' => $latestApp->created_at,
                'reviewed_at' => $latestApp->reviewed_at,
            ] : null,
            'kycStatus' => $latestVer ? ($latestVer->status instanceof \BackedEnum ? $latestVer->status->value : $latestVer->status) : null,
            'kycDocumentsCount' => $latestVer ? $latestVer->documents->count() : 0,
        ]);
    }

    private function canImpersonate(?User $admin, User $target): bool
    {
        if (! $admin || ! $admin->isAdmin()) {
            return false;
        }

        return app(ImpersonationService::class)->canImpersonate($admin, $target);
    }
}
