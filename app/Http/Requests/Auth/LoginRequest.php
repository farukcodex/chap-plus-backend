<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
        return [
            'email'           => 'required|email',
            'password'        => 'required|string|min:8',
            'expo_push_token' => ['nullable', 'string', 'max:255', 'regex:/^(ExponentPushToken|ExpoPushToken)\[.*\]$/'],
            'platform'        => 'nullable|string|in:android,ios,web',
            'device_name'     => 'nullable|string|max:100',
            'device_id'       => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'          => 'An email address is required.',
            'email.email'             => 'Please provide a valid email address.',

            'password.required'       => 'A password is required.',
            'password.min'            => 'The password must be at least 8 characters long.',

            'expo_push_token.regex'   => 'The expo push token format is invalid. Expected ExponentPushToken[...] or ExpoPushToken[...]',
        ];
    }
}
