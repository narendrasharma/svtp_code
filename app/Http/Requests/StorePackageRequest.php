<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'exists:cities,id'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'duration_nights' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'overview' => ['nullable', 'string'],
            'day_wise_itinerary' => ['nullable', 'array'],
            'day_wise_itinerary.*.day' => ['nullable', 'integer', 'min:1'],
            'day_wise_itinerary.*.title' => ['nullable', 'string', 'max:255'],
            'day_wise_itinerary.*.points' => ['nullable', 'array'],
            'day_wise_itinerary.*.points.*' => ['nullable', 'string', 'max:500'],
            'inclusions' => ['nullable', 'array'],
            'inclusions.*' => ['nullable', 'string', 'max:500'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['nullable', 'string', 'max:500'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['nullable', 'string', 'max:2048'],
            'gallery_uploads' => ['nullable', 'array', 'max:12'],
            'gallery_uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'category_id' => ['nullable', 'integer', 'exists:tour_categories,id'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'destination_ids' => ['present', 'array'],
            'destination_ids.*' => ['integer', 'distinct', 'exists:destinations,id'],
            'place_ids' => ['present', 'array'],
            'place_ids.*' => ['integer', 'distinct', 'exists:places,id'],
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
        ];
    }
}
