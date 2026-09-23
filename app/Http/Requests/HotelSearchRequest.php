<?php

namespace App\Http\Requests;

use App\Models\HotelRatePlan;
use App\Services\Discovery\HotelSearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hotel search validation (Phase 13C).
 *
 * Sort keys whitelist-mapped downstream; arbitrary columns can never
 * reach orderBy. Dates use property-local semantics in the service.
 */
class HotelSearchRequest extends FormRequest
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
            'check_in' => ['nullable', 'date_format:Y-m-d', 'required_with:check_out'],
            'check_out' => ['nullable', 'date_format:Y-m-d', 'required_with:check_in', 'after:check_in'],
            'rooms' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'property_type_id' => ['nullable', 'integer', 'exists:property_types,id'],
            'star_rating' => ['nullable', 'integer', 'in:1,2,3,4,5'],
            'amenities' => ['sometimes', 'array', 'max:10'],
            'amenities.*' => ['integer', 'exists:hotel_amenities,id'],
            'meal_plan' => ['nullable', Rule::in(HotelRatePlan::MEAL_PLANS)],
            'cancellation_mode' => ['nullable', Rule::in(HotelRatePlan::CANCELLATION_MODES)],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'price_currency' => ['nullable', 'string', 'size:3'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'sort' => ['sometimes', Rule::in(HotelSearchService::allowedSorts())],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ];
    }
}
