<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentCopilotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isVendor();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedContext = match ($this->input('content_type')) {
            'tour' => 'title,destination,duration_days,category,places,notes,existing_itinerary',
            'hotel' => 'name,type,city',
            'page', 'destination', 'place' => 'name,excerpt,destination',
            default => '',
        };

        return [
            'content_type' => ['required', Rule::in(['tour', 'hotel', 'page', 'destination', 'place'])],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['improve', 'rewrite', 'shorten', 'expand', 'fix_grammar', 'generate', 'seo', 'itinerary'])],
            'field' => ['required', Rule::in(['overview', 'content', 'description', 'seo', 'itinerary'])],
            'output_format' => ['nullable', Rule::in(['html', 'plain'])],
            'source' => ['nullable', 'string', 'max:12000'],
            'context' => ['nullable', 'array:'.$allowedContext],
            'context.title' => ['nullable', 'string', 'max:200'],
            'context.name' => ['nullable', 'string', 'max:200'],
            'context.excerpt' => ['nullable', 'string', 'max:500'],
            'context.destination' => ['nullable', 'string', 'max:150'],
            'context.duration_days' => ['nullable', 'integer', 'between:1,30'],
            'context.category' => ['nullable', 'string', 'max:100'],
            'context.city' => ['nullable', 'string', 'max:100'],
            'context.type' => ['nullable', 'string', 'max:100'],
            'context.notes' => ['nullable', 'string', 'max:1000'],
            'context.places' => ['nullable', 'array', 'max:15'],
            'context.places.*' => ['string', 'max:100'],
            'context.existing_itinerary' => ['nullable', 'array', 'max:30'],
            'context.existing_itinerary.*' => ['array:day,title,points'],
            'context.existing_itinerary.*.day' => ['nullable', 'integer', 'between:1,30'],
            'context.existing_itinerary.*.title' => ['nullable', 'string', 'max:150'],
            'context.existing_itinerary.*.points' => ['nullable', 'array', 'max:10'],
            'context.existing_itinerary.*.points.*' => ['string', 'max:300'],
        ];
    }
}
