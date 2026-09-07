<?php

namespace App\Http\Requests\Rider;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('type') && is_string($this->type)) {
            $this->merge([
                'type' => strtolower($this->type),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type'     => 'nullable|string|in:ecommerce,restaurant',
            'filter'   => 'nullable|string|in:ready_for_pickup,accepted,picked_up,on_the_way,delivered,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in'      => 'The type must be either ecommerce or restaurant.',
            'filter.in'    => 'The filter must be ready_for_pickup, accepted, picked_up, on_the_way, delivered, or all.',
            'per_page.min' => 'The per_page must be at least 1.',
            'per_page.max' => 'The per_page must not exceed 100.',
        ];
    }
}
