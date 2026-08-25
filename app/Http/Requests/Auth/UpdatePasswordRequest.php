<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
            'password_reset_token' => 'required|string',
        ];
    }

        public function messages(): array
    {
        return [
            'password.required'  => 'Password is required.',
            'password.string'    => 'Password must be a valid string.',
            'password.min'       => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Passwords do not match.',
            'password_reset_token.required' => 'Password reset token is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.'

        ];
    }
}
