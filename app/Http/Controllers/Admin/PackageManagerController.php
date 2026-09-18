<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TourModerationStatus;
use App\Events\TourModerated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TourCategory;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PackageManagerController extends Controller
{
    public function index(Request $request): Response
    {
        // Base query with required relationships
        $query = TourPackage::with(['city', 'category', 'vendorProfile', 'creator']);

        // -----------------------------------------------------------------
        // Search (title, slug, code/reference if column exists)
        // -----------------------------------------------------------------
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
                // If a package code/reference column exists, include it safely
                if (in_array('code', \Schema::getColumnListing((new TourPackage)->getTable()))) {
                    $q->orWhere('code', 'like', "%{$search}%");
                }
            });
        }

        // -----------------------------------------------------------------
        // Status filter (active / inactive)
        // -----------------------------------------------------------------
        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // -----------------------------------------------------------------
        // Moderation status filter
        // -----------------------------------------------------------------
        if ($moderation = $request->query('moderation_status')) {
            if (in_array($moderation, TourModerationStatus::values())) {
                $query->where('moderation_status', $moderation);
            }
        }

        // -----------------------------------------------------------------
        // Owner filter (admin vs vendor)
        // -----------------------------------------------------------------
        if ($owner = $request->query('owner')) {
            if ($owner === 'admin') {
                $query->whereNull('vendor_profile_id');
            } elseif ($owner === 'vendor') {
                $query->whereNotNull('vendor_profile_id');
            }
        }

        // -----------------------------------------------------------------
        // Category filter – use the actual relationship (many‑to‑one or many‑to‑many)
        // -----------------------------------------------------------------
        if ($category = $request->query('category')) {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('id', $category);
            });
        }

        // -----------------------------------------------------------------
        // City filter – use the actual relationship (many‑to‑one or many‑to‑many)
        // -----------------------------------------------------------------
        if ($city = $request->query('city')) {
            $query->whereHas('city', function ($q) use ($city) {
                $q->where('id', $city);
            });
        }

        // -----------------------------------------------------------------
        // Sorting
        // -----------------------------------------------------------------
        $sortable = ['title', 'price', 'created_at', 'is_active'];
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, $sortable)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        // -----------------------------------------------------------------
        // Per‑page selector
        // -----------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $packages = $query->paginate($perPage)->appends($request->query());

        return Inertia::render('Admin/Packages/Index', [
            'packages' => $packages,
            'categories' => TourCategory::orderBy('name')->get(['id', 'name']),
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'moderation_status' => $moderation ?? '',
                'owner' => $owner ?? '',
                'category' => $category ?? '',
                'city' => $city ?? '',
                'per_page' => $perPage,
                'sort' => $sort ?? '',
                'direction' => $direction ?? '',
            ],
            'moderationStatuses' => collect(TourModerationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Packages/Form', $this->formOptions());
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $this->packageData($request);
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(4);
            $data['vendor_profile_id'] = null;
            $data['created_by'] = $request->user()->id;
            $data['moderation_status'] = TourModerationStatus::Approved;
            $data['is_active'] = $request->boolean('is_active', true);

            $package = TourPackage::create($data);
            $this->syncClassifications($package, $request);
            $this->recordHistory($package, null, TourModerationStatus::Approved->value, $request->user()->id, 'Admin created');
        });
        Cache::forget('home.featured_packages');

        return redirect()->route('admin.packages.index')->with('flash', 'Package created.');
    }

    public function show(TourPackage $package): Response
    {
        $package->load(['city', 'category', 'vendorProfile', 'creator:id,name', 'reviewer:id,name', 'destinations:id,name', 'places:id,name', 'tags:id,name', 'moderationHistories.changer:id,name']);
        $vendorKyc = null;
        if ($package->vendorProfile) {
            $vendorUser = $package->vendorProfile->user;
            $latestVer = $vendorUser?->vendorVerifications()->latest()->first();
            $vendorKyc = $latestVer?->status?->value ?? $latestVer?->status;
        }

        return Inertia::render('Admin/Packages/Show', [
            'package' => $package,
            'vendorKycStatus' => $vendorKyc,
            'moderationStatuses' => collect(TourModerationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function edit(TourPackage $package): Response
    {
        $package->load(['category:id,name', 'destinations:id', 'places:id', 'tags:id', 'vendorProfile:id,business_name', 'creator:id,name']);

        return Inertia::render('Admin/Packages/Form', [
            ...$this->formOptions(),
            'package' => $package,
            'moderationStatuses' => collect(TourModerationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function update(StorePackageRequest $request, TourPackage $package): RedirectResponse
    {
        DB::transaction(function () use ($request, $package): void {
            $data = $this->packageData($request, $package);
            // Admin can update moderation_status directly if needed, but keep approved by default
            $package->update($data);
            $this->syncClassifications($package, $request);
        });
        Cache::forget('home.featured_packages');

        return redirect()->route('admin.packages.index')->with('flash', 'Package updated.');
    }

    public function destroy(TourPackage $package): RedirectResponse
    {
        if ($package->bookings()->exists()) {
            return back()->withErrors(['package' => 'Cannot delete package with bookings. Deactivate instead.']);
        }
        // Cleanup images
        foreach ($package->gallery ?? [] as $img) {
            $this->deleteManagedImage($img, 'packages/gallery');
        }
        $this->deleteManagedImage($package->cover_image, 'packages/covers');
        $package->destinations()->detach();
        $package->places()->detach();
        $package->tags()->detach();
        $package->delete();
        Cache::forget('home.featured_packages');

        return back()->with('flash', 'Package removed.');
    }

    public function approve(Request $request, TourPackage $package): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $from = $package->moderation_status instanceof TourModerationStatus ? $package->moderation_status->value : $package->moderation_status;
        $package->update([
            'moderation_status' => TourModerationStatus::Approved,
            'is_active' => true,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('note'),
        ]);
        $this->recordHistory($package, $from, TourModerationStatus::Approved->value, $request->user()->id, $request->input('note'));
        Cache::forget('home.featured_packages');
        TourModerated::dispatch($package->refresh(), 'approved');

        return back()->with('success', 'Tour approved and published.');
    }

    public function requestChanges(Request $request, TourPackage $package): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $from = $package->moderation_status instanceof TourModerationStatus ? $package->moderation_status->value : $package->moderation_status;
        $package->update([
            'moderation_status' => TourModerationStatus::ChangesRequested,
            'is_active' => false,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('note'),
        ]);
        $this->recordHistory($package, $from, TourModerationStatus::ChangesRequested->value, $request->user()->id, $request->input('note'));
        Cache::forget('home.featured_packages');
        TourModerated::dispatch($package->refresh(), 'changes_requested');

        return back()->with('success', 'Changes requested.');
    }

    public function reject(Request $request, TourPackage $package): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $from = $package->moderation_status instanceof TourModerationStatus ? $package->moderation_status->value : $package->moderation_status;
        $package->update([
            'moderation_status' => TourModerationStatus::Rejected,
            'is_active' => false,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('note'),
        ]);
        $this->recordHistory($package, $from, TourModerationStatus::Rejected->value, $request->user()->id, $request->input('note'));
        Cache::forget('home.featured_packages');
        TourModerated::dispatch($package->refresh(), 'rejected');

        return back()->with('success', 'Tour rejected.');
    }

    private function recordHistory(TourPackage $package, ?string $from, string $to, ?int $userId, ?string $note): void
    {
        $package->moderationHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }

    /**
     * @return array{cities: mixed, categories: mixed, destinations: mixed, places: mixed, tags: mixed}
     */
    private function formOptions(): array
    {
        return [
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'categories' => TourCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']),
            'destinations' => Destination::orderBy('name')->get(['id', 'name']),
            'places' => Place::with('destination:id,name')
                ->orderBy('name')->get(['id', 'destination_id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'is_active']),
        ];
    }

    private function syncClassifications(TourPackage $package, StorePackageRequest $request): void
    {
        $package->destinations()->sync($request->validated('destination_ids'));
        $package->places()->sync($request->validated('place_ids'));
        $package->tags()->sync($request->validated('tag_ids'));
    }

    /**
     * @return array<string, mixed>
     */
    private function packageData(StorePackageRequest $request, ?TourPackage $package = null): array
    {
        $data = $request->safe()->except([
            'destination_ids',
            'place_ids',
            'tag_ids',
            'gallery_uploads',
            'cover_image_upload',
            'remove_cover_image',
        ]);
        $gallery = $data['gallery'] ?? $package?->gallery ?? [];

        foreach ($request->file('gallery_uploads', []) as $image) {
            $gallery[] = Storage::disk('public')->url($image->store('packages/gallery', 'public'));
        }

        $data['gallery'] = array_values(array_filter($gallery));

        if ($request->hasFile('cover_image_upload')) {
            $data['cover_image'] = Storage::disk('public')->url($request->file('cover_image_upload')->store('packages/covers', 'public'));
        } elseif ($package && $request->boolean('remove_cover_image')) {
            $data['cover_image'] = null;
        }

        if ($package) {
            $removedImages = array_diff($package->gallery ?? [], $data['gallery']);

            foreach ($removedImages as $removedImage) {
                $this->deleteManagedImage($removedImage, 'packages/gallery');
            }

            if (array_key_exists('cover_image', $data) && $package->cover_image !== $data['cover_image']) {
                $this->deleteManagedImage($package->cover_image, 'packages/covers');
            }
        }

        return $data;
    }

    private function deleteManagedImage(?string $image, string $directory): void
    {
        $path = $image ? parse_url($image, PHP_URL_PATH) : null;
        $prefix = '/storage/'.$directory.'/';

        if (is_string($path) && str_starts_with($path, $prefix)) {
            Storage::disk('public')->delete(substr($path, strlen('/storage/')));
        }
    }
}
