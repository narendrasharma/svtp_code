<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\HotelAmenity;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\HotelHtml;
use App\Support\HotelSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hotel property domain service (12B.1).
 *
 * Server-authoritative ownership, slugs, statuses and relations.
 * Vendor IDs, publish transitions and amenity sets never come from
 * raw request input — controllers pass validated content only.
 */
class HotelPropertyService
{
    /**
     * Validation rules shared by Admin and Vendor controllers.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?Property $property = null): array
    {
        return [
            'property_type_id' => ['required', 'integer', 'exists:property_types,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:properties,slug'.($property ? ','.$property->id : '')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_featured' => ['sometimes', 'boolean'],
            'star_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['nullable', 'numeric', 'min:-180', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:255'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'max:60', 'timezone'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'children_policy' => ['nullable', 'string', 'max:255'],
            'pet_policy' => ['nullable', 'string', 'max:255'],
            'smoking_policy' => ['nullable', 'string', 'max:255'],
            'check_in_instructions' => ['nullable', 'string', 'max:2000'],
            'house_rules' => ['nullable', 'string', 'max:2000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'amenity_ids' => ['nullable', 'array', 'max:50'],
            'amenity_ids.*' => ['integer', 'exists:hotel_amenities,id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated content fields only
     */
    public function create(array $data, ?User $actor, ?int $vendorProfileId, bool $publishDirectly = false): Property
    {
        return DB::transaction(function () use ($data, $actor, $vendorProfileId, $publishDirectly): Property {
            $attributes = $this->contentAttributes($data, true);
            $this->assertValidGeography($attributes);

            if (! $this->canPublish($actor, null)) {
                unset($attributes['is_featured']);
            }

            $property = Property::create([
                ...$attributes,
                'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name']),
            ]);

            $property->forceFill([
                'vendor_profile_id' => $vendorProfileId,
                'status' => $publishDirectly ? PropertyStatus::Published->value : PropertyStatus::Draft->value,
                'published_at' => $publishDirectly ? now() : null,
                'created_by' => $actor?->id,
            ])->save();

            $this->syncAmenities($property, $data['amenity_ids'] ?? []);

            return $property->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated content fields only
     */
    public function update(Property $property, array $data, ?User $actor = null): Property
    {
        return DB::transaction(function () use ($property, $data, $actor): Property {
            $attributes = $this->contentAttributes($data, false);
            $this->assertValidGeography(array_merge(
                $property->only(['country_id', 'state_id', 'city_id', 'destination_id']),
                $attributes
            ));

            if (! $this->canPublish($actor, $property)) {
                unset($attributes['is_featured']);
            }

            $property->fill($attributes);

            if (! empty($data['slug']) && $data['slug'] !== $property->getOriginal('slug')) {
                $property->slug = $this->uniqueSlug($data['slug'], $property->name, $property->id);
            }

            // Editing a rejected property returns it to draft for re-review.
            if ($property->status === PropertyStatus::Rejected->value && ! $this->canPublish($actor, $property)) {
                $property->forceFill(['status' => PropertyStatus::Draft->value]);
            }

            $property->save();
            $this->syncAmenities($property, $data['amenity_ids'] ?? []);

            return $property->fresh();
        });
    }

    public function setFeatured(Property $property, bool $featured, User $actor): Property
    {
        if (! $this->canPublish($actor, $property)) {
            throw ValidationException::withMessages(['is_featured' => 'You cannot change property merchandising.']);
        }

        if ($featured && $property->status !== PropertyStatus::Published->value) {
            throw ValidationException::withMessages(['is_featured' => 'Only published properties can be featured.']);
        }

        $property->fill(['is_featured' => $featured])->save();

        return $property->fresh();
    }

    public function submit(Property $property, User $actor): Property
    {
        return DB::transaction(function () use ($property, $actor): Property {
            $property = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();

            if (! in_array($property->status, [PropertyStatus::Draft->value, PropertyStatus::Rejected->value], true)) {
                throw ValidationException::withMessages(['status' => 'Only draft or rejected properties can be submitted.']);
            }

            if (! HotelSettings::enabled('hotel.require_property_approval')) {
                return $this->publish($property->fresh(), $actor);
            }

            $property->forceFill([
                'status' => PropertyStatus::PendingReview->value,
            ])->save();

            $this->notifyAdmins($property->fresh(), 'hotel_property_submitted');

            return $property->fresh();
        }, 3);
    }

    public function publish(Property $property, ?User $actor, ?string $note = null): Property
    {
        return $this->transition($property, PropertyStatus::Published, $actor, $note, true);
    }

    public function reject(Property $property, ?User $actor, ?string $note = null): Property
    {
        return $this->transition($property, PropertyStatus::Rejected, $actor, $note, false);
    }

    public function deactivate(Property $property, ?User $actor): Property
    {
        return $this->transition($property, PropertyStatus::Inactive, $actor, null, false);
    }

    protected function transition(Property $property, PropertyStatus $to, ?User $actor, ?string $note, bool $published): Property
    {
        return DB::transaction(function () use ($property, $to, $note, $published): Property {
            $property = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();

            $property->forceFill([
                'status' => $to->value,
                'published_at' => $published ? ($property->published_at ?? now()) : $property->published_at,
            ])->save();

            $fresh = $property->fresh();

            try {
                $fresh->vendorProfile?->user?->notify(new CrmNotification(
                    $to === PropertyStatus::Published ? 'hotel_property_approved' : 'hotel_property_rejected',
                    ['property_id' => $fresh->id, 'property' => $fresh->name, 'note' => $note]
                ));
            } catch (\Throwable) {
            }

            return $fresh;
        }, 3);
    }

    /**
     * @param  array<int, mixed>  $amenityIds
     */
    public function syncAmenities(Property $property, array $amenityIds): void
    {
        $ids = HotelAmenity::whereIn('id', array_map('intval', $amenityIds))
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $property->amenities()->sync($ids);
    }

    public function addImage(Property $property, UploadedFile $file, ?string $altText = null): PropertyImage
    {
        $path = $file->store('properties/'.$property->id, 'public');

        $image = $property->images()->create([
            'path' => $path,
            'alt_text' => $altText !== null ? mb_substr(trim(strip_tags($altText)), 0, 150) : null,
            'sort_order' => (int) ($property->images()->max('sort_order') ?? -1) + 1,
            'is_primary' => $property->images()->count() === 0,
        ]);

        return $image->fresh();
    }

    public function removeImage(Property $property, PropertyImage $image): void
    {
        abort_unless((int) $image->property_id === (int) $property->id, 404);

        DB::transaction(function () use ($property, $image): void {
            Storage::disk('public')->delete($image->path);
            $wasPrimary = $image->is_primary;
            $image->delete();

            if ($wasPrimary) {
                $next = $property->images()->orderBy('sort_order')->first();

                if ($next) {
                    $next->forceFill(['is_primary' => true])->save();
                }
            }
        });
    }

    public function setPrimaryImage(Property $property, PropertyImage $image): PropertyImage
    {
        abort_unless((int) $image->property_id === (int) $property->id, 404);

        return DB::transaction(function () use ($property, $image): PropertyImage {
            $property->images()->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true])->save();

            return $image->fresh();
        });
    }

    public function delete(Property $property): void
    {
        // Archive-friendly soft delete: gallery rows and files stay intact
        // so a restore brings the listing back whole. Pivot rows are
        // booking-free metadata and remain attached for history.
        $property->delete();
    }

    public function uniqueSlug(?string $desired, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) ($desired !== null && trim($desired) !== '' ? $desired : $name)) ?: 'property');
        $base = mb_substr($base !== '' ? $base : 'property', 0, 150);
        $slug = $base;
        $counter = 2;

        while (Property::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->withTrashed()->exists()) {
            $slug = mb_substr($base, 0, 170).'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Shared geography hierarchy guard (12B.4.1): a property can never
     * combine a city with a foreign state/country, or a destination
     * from an incompatible city/country. Nulls stay permissive — a
     * property with a valid city but no destination is perfectly valid.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function assertValidGeography(array $attributes): void
    {
        $id = fn (string $key): ?int => isset($attributes[$key]) && $attributes[$key] !== null
            ? (int) $attributes[$key]
            : null;

        LocationHierarchy::validatePropertyGeography(
            $id('country_id'),
            $id('state_id'),
            $id('city_id'),
            $id('destination_id'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data, bool $applyDefaults): array
    {
        $attributes = (new Property)->getFillable();
        $out = [];

        foreach ($attributes as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $out[$key] = $data[$key];
        }

        if (array_key_exists('description', $out)) {
            $out['description'] = HotelHtml::clean($out['description'] !== null ? (string) $out['description'] : null);
        }

        foreach (['children_policy', 'pet_policy', 'smoking_policy', 'check_in_instructions', 'house_rules', 'short_description'] as $plain) {
            if (array_key_exists($plain, $out) && $out[$plain] !== null) {
                $out[$plain] = trim(strip_tags((string) $out[$plain]));
            }
        }

        if (! empty($out['country_code'])) {
            $out['country_code'] = strtoupper((string) $out['country_code']);
        }

        if (! empty($out['currency'])) {
            $out['currency'] = strtoupper((string) $out['currency']);
        } elseif ($applyDefaults) {
            $out['currency'] = HotelSettings::defaultCurrency();
        }

        if (empty($out['timezone']) && $applyDefaults) {
            $out['timezone'] = HotelSettings::defaultTimezone();
        }

        return $out;
    }

    protected function canPublish(?User $actor, ?Property $property): bool
    {
        return $actor !== null && ($actor->isAdmin() || $actor->can('hotel.properties.publish'));
    }

    protected function notifyAdmins(Property $property, string $kind): void
    {
        try {
            foreach (User::where('role', 'admin')->limit(25)->get() as $admin) {
                $admin->notify(new CrmNotification($kind, [
                    'property_id' => $property->id,
                    'property' => $property->name,
                ]));
            }
        } catch (\Throwable) {
        }
    }
}
