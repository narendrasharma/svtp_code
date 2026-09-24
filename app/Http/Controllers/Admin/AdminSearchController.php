<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\AdminSearch\AdminSearchService;
use App\Support\ModuleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global admin search / command palette backend (Phase 11.5A).
 *
 * Debounced async search across permission-filtered providers plus the
 * minimal async option lists that power SmartSelect on large datasets.
 * Neither endpoint ever returns sensitive fields.
 */
class AdminSearchController extends Controller
{
    public function search(Request $request, AdminSearchService $search): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        return response()->json($search->search($request->user(), $query));
    }

    public function selectOptions(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', '');
        $query = trim(mb_substr((string) $request->query('search', ''), 0, 80));
        $user = $request->user();

        $options = match ($type) {
            'users' => $this->userOptions($user, $query),
            'vendors' => $this->vendorOptions($user, $query),
            'tours' => $this->tourOptions($user, $query),
            'bookings' => $this->bookingOptions($user, $query),
            'leads' => $this->leadOptions($user, $query),
            default => null,
        };

        if ($options === null) {
            return response()->json(['message' => 'Unknown option type.'], 422);
        }

        return response()->json(['type' => $type, 'options' => $options]);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function userOptions(?User $user, string $query): ?array
    {
        if ($user === null || ! $user->can('users.view')) {
            abort(403);
        }

        $term = '%'.$query.'%';

        return User::query()
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $found): array => [
                'value' => $found->id,
                'label' => $found->name,
                'meta' => $found->email,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function vendorOptions(?User $user, string $query): ?array
    {
        if ($user === null || ! $user->can('vendors.view')) {
            abort(403);
        }

        $term = '%'.$query.'%';

        return VendorProfile::query()
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('business_name', 'like', $term)
                ->orWhere('city', 'like', $term)))
            ->orderBy('business_name')
            ->limit(20)
            ->get(['id', 'business_name', 'city'])
            ->map(fn (VendorProfile $profile): array => [
                'value' => $profile->id,
                'label' => $profile->business_name,
                'meta' => $profile->city,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function tourOptions(?User $user, string $query): ?array
    {
        if ($user === null || ! $user->can('tours.view')) {
            abort(403);
        }

        if (app(ModuleManager::class)->isDisabled(ModuleManager::TOURS)) {
            return [];
        }

        $term = '%'.$query.'%';

        return TourPackage::query()
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', $term)
                ->orWhere('slug', 'like', $term)))
            ->orderBy('title')
            ->limit(20)
            ->get(['id', 'title'])
            ->map(fn (TourPackage $package): array => [
                'value' => $package->id,
                'label' => $package->title,
                'meta' => null,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function leadOptions(?User $user, string $query): ?array
    {
        if ($user === null || ! $user->can('leads.view')) {
            abort(403);
        }

        $term = '%'.$query.'%';

        return Lead::visibleTo($user)
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('phone', 'like', $term)))
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'reference', 'name', 'phone'])
            ->map(fn (Lead $lead): array => [
                'value' => $lead->id,
                'label' => $lead->reference.' · '.$lead->name,
                'meta' => trim('ID #'.$lead->id.' · '.($lead->phone ?? '')),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function bookingOptions(?User $user, string $query): ?array
    {
        if ($user === null || ! $user->can('bookings.view')) {
            abort(403);
        }

        $term = '%'.$query.'%';

        return Booking::query()
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('booking_reference_id', 'like', $term)
                ->orWhere('customer_name', 'like', $term)))
            ->latest()
            ->limit(20)
            ->get(['id', 'booking_reference_id', 'customer_name'])
            ->map(fn (Booking $booking): array => [
                'value' => $booking->id,
                'label' => $booking->booking_reference_id,
                'meta' => $booking->customer_name,
            ])
            ->all();
    }
}
