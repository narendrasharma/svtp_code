<?php

namespace App\Http\Requests;

use App\Services\Discovery\TourSearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tour search validation (Phase 13C).
 */
class TourSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'location_type' => ['nullable', Rule::in(['city', 'destination', 'place'])],
            'location_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'travel_date' => ['nullable', 'date_format:Y-m-d'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'category_id' => ['nullable', 'integer', 'exists:tour_categories,id'],
            'min_duration' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_duration' => ['nullable', 'integer', 'min:1', 'max:365'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'featured' => ['sometimes', 'boolean'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'sort' => ['sometimes', Rule::in(TourSearchService::allowedSorts())],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ];
    }
}
