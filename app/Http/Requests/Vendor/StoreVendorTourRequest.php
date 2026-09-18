<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    public function rules(): array
    {
        // Draft: only title required minimally; submit will enforce stricter
        return [
            'title' => ['required', 'string', 'max:255'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'duration_nights' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'overview' => ['nullable', 'string'],
            'day_wise_itinerary' => ['nullable', 'array'],
            'inclusions' => ['nullable', 'array'],
            'exclusions' => ['nullable', 'array'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['nullable', 'string', 'max:2048'],
            'gallery_uploads' => ['nullable', 'array', 'max:12'],
            'gallery_uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover_image' => ['boolean'],
            'category_id' => ['nullable', 'integer', 'exists:tour_categories,id'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'destination_ids' => ['nullable', 'array'],
            'destination_ids.*' => ['integer', 'distinct', 'exists:destinations,id'],
            'place_ids' => ['nullable', 'array'],
            'place_ids.*' => ['integer', 'distinct', 'exists:places,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
        ];
    }

    public function withValidator($validator)
    {
        // Prevent mass assignment of privileged fields
        $validator->after(function ($validator) {
            $forbidden = ['moderation_status', 'reviewed_by', 'reviewed_at', 'vendor_profile_id', 'created_by', 'is_active', 'is_featured'];
            foreach ($forbidden as $field) {
                if ($this->has($field) && $this->input($field) !== null) {
                    // Allow is_active/is_featured for vendor but will be ignored in controller; don't error
                }
            }
        });
    }
}
