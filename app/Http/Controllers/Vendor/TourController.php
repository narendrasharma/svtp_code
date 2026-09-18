<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\TourModerationStatus;
use App\Events\TourSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreVendorTourRequest;
use App\Models\City;
use App\Models\Destination;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TourCategory;
use App\Models\TourPackage;
use App\Services\VendorEntitlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TourController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->vendorProfile;
        abort_unless($profile, 403);

        $query = TourPackage::with(['city', 'category', 'vendorProfile'])
            ->where('vendor_profile_id', $profile->id);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('moderation_status')) {
            if (in_array($status, TourModerationStatus::values())) {
                $query->where('moderation_status', $status);
            }
        }

        if ($request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

        $tours = $query->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('Vendor/Tours/Index', [
            'tours' => $tours,
            'filters' => $request->only(['search', 'moderation_status', 'is_active', 'per_page']),
            'moderationStatuses' => collect(TourModerationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'kycStatus' => $profile ? null : null, // will be handled via dashboard warning
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', TourPackage::class);

        return Inertia::render('Vendor/Tours/Form', $this->formOptions());
    }

    public function store(StoreVendorTourRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->vendorProfile;
        abort_unless($profile, 403);

        $this->authorize('create', TourPackage::class);

        $package = DB::transaction(function () use ($request, $profile, $user) {
            $data = $this->packageData($request);
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(4);
            $data['vendor_profile_id'] = $profile->id;
            $data['created_by'] = $user->id;
            $data['moderation_status'] = TourModerationStatus::Draft;
            $data['is_active'] = false;
            $package = TourPackage::create($data);
            $this->syncClassifications($package, $request);
            $this->recordHistory($package, null, TourModerationStatus::Draft->value, $user->id, 'Created as draft');

            return $package;
        });

        Cache::forget('home.featured_packages');

        return redirect()->route('vendor.tours.index')->with('success', 'Tour created as draft.');
    }

    public function edit(TourPackage $tour): Response
    {
        $this->authorize('update', $tour);
        $tour->load(['category:id,name', 'destinations:id', 'places:id', 'tags:id']);

        return Inertia::render('Vendor/Tours/Form', [
            ...$this->formOptions(),
            'tour' => $tour,
        ]);
    }

    public function update(StoreVendorTourRequest $request, TourPackage $tour): RedirectResponse
    {
        $this->authorize('update', $tour);
        $user = $request->user();

        $wasApproved = $tour->moderation_status === TourModerationStatus::Approved;

        DB::transaction(function () use ($request, $tour, $user, $wasApproved) {
            $data = $this->packageData($request, $tour);
            // Vendor cannot directly set moderation_status or reviewed fields
            // If editing approved tour, reset to draft and deactivate
            if ($wasApproved) {
                $data['moderation_status'] = TourModerationStatus::Draft;
                $data['is_active'] = false;
                $data['submitted_at'] = null;
                $data['reviewed_by'] = null;
                $data['reviewed_at'] = null;
                $data['review_note'] = null;
            }
            $tour->update($data);
            $this->syncClassifications($tour, $request);
            if ($wasApproved) {
                $this->recordHistory($tour, TourModerationStatus::Approved->value, TourModerationStatus::Draft->value, $user->id, 'Edited approved tour, reset to draft');
            }
        });

        Cache::forget('home.featured_packages');

        return redirect()->route('vendor.tours.index')->with('success', $wasApproved ? 'Tour updated and reset to draft for re-review.' : 'Tour updated.');
    }

    public function submit(Request $request, TourPackage $tour): RedirectResponse
    {
        $this->authorize('update', $tour);
        $user = $request->user();

        if (! in_array($tour->moderation_status->value, [TourModerationStatus::Draft->value, TourModerationStatus::ChangesRequested->value, TourModerationStatus::Rejected->value])) {
            return back()->withErrors(['tour' => 'Only draft, changes requested or rejected tours can be submitted.']);
        }

        // Phase 11: plan entitlement gates submission (drafts are always
        // allowed; only approved+active tours count toward the limit).
        $profile = $user->vendorProfile;

        if ($profile) {
            app(VendorEntitlementService::class)->assertCanSubmitTour($profile->refresh());
        }

        // Ensure required publication fields are present on the tour itself
        if (! $tour->city_id || ! $tour->duration_days || ! $tour->price || ! $tour->overview) {
            return back()->withErrors(['tour' => 'Complete required fields before submitting.']);
        }

        $from = $tour->moderation_status->value;
        $tour->update([
            'moderation_status' => TourModerationStatus::PendingReview,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);
        $this->recordHistory($tour, $from, TourModerationStatus::PendingReview->value, $user->id, 'Submitted for review');
        Cache::forget('home.featured_packages');
        TourSubmitted::dispatch($tour->refresh());

        return back()->with('success', 'Tour submitted for review.');
    }

    public function destroy(Request $request, TourPackage $tour): RedirectResponse
    {
        $this->authorize('delete', $tour);
        if ($tour->bookings()->exists()) {
            return back()->withErrors(['tour' => 'Cannot delete tour with booking history. Deactivate instead.']);
        }
        // Clean up images
        $this->cleanupImages($tour);
        $tour->destinations()->detach();
        $tour->places()->detach();
        $tour->tags()->detach();
        $tour->delete();
        Cache::forget('home.featured_packages');

        return redirect()->route('vendor.tours.index')->with('success', 'Tour deleted.');
    }

    private function formOptions(): array
    {
        return [
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'categories' => TourCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']),
            'destinations' => Destination::orderBy('name')->get(['id', 'name']),
            'places' => Place::with('destination:id,name')->orderBy('name')->get(['id', 'destination_id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'is_active']),
        ];
    }

    private function syncClassifications(TourPackage $package, $request): void
    {
        $package->destinations()->sync($request->input('destination_ids', []));
        $package->places()->sync($request->input('place_ids', []));
        $package->tags()->sync($request->input('tag_ids', []));
    }

    private function packageData($request, ?TourPackage $package = null): array
    {
        $data = $request->safe()->except([
            'destination_ids', 'place_ids', 'tag_ids', 'gallery_uploads', 'cover_image_upload', 'remove_cover_image',
            'moderation_status', 'reviewed_by', 'reviewed_at', 'vendor_profile_id', 'created_by', 'review_note',
        ]);
        // Vendor cannot set is_featured
        unset($data['is_featured']);
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

    private function cleanupImages(TourPackage $tour): void
    {
        foreach ($tour->gallery ?? [] as $img) {
            $this->deleteManagedImage($img, 'packages/gallery');
        }
        $this->deleteManagedImage($tour->cover_image, 'packages/covers');
    }

    private function recordHistory(TourPackage $tour, ?string $from, string $to, ?int $userId, ?string $note): void
    {
        $tour->moderationHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }
}
