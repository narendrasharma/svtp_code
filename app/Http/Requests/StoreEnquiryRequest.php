<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnquiryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isTourPlan = $this->input('enquiry_type') === 'tour_plan';

        return [
            'enquiry_type' => ['required', Rule::in(['tour_plan', 'quick'])],
            'tour_package_id' => [$isTourPlan ? 'nullable' : 'prohibited', 'integer', 'exists:tour_packages,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'email' => [$isTourPlan ? 'nullable' : 'prohibited', 'email', 'max:255'],
            'pickup_drop' => [$isTourPlan ? 'required' : 'prohibited', 'string', 'max:255'],
            'hotel_category' => [$isTourPlan ? 'required' : 'prohibited', 'string', Rule::in(['budget', 'standard', 'deluxe', 'premium'])],
            'adults' => [$isTourPlan ? 'required' : 'prohibited', 'integer', 'min:1', 'max:100'],
            'children' => [$isTourPlan ? 'nullable' : 'prohibited', 'integer', 'min:0', 'max:100'],
            'arrival_date' => [$isTourPlan ? 'required' : 'prohibited', 'date', 'after_or_equal:today'],
            'departure_date' => [$isTourPlan ? 'required' : 'prohibited', 'date', 'after_or_equal:arrival_date'],
            'message' => [$isTourPlan ? 'nullable' : 'prohibited', 'string', 'max:2000'],
            'security_answer' => [
                $isTourPlan ? 'required' : 'prohibited',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->session()->has('enquiry_math_answer')
                        || (int) $value !== (int) $this->session()->get('enquiry_math_answer')) {
                        $fail('The math security answer is incorrect.');
                    }
                },
            ],
        ];
    }
}
