<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class UserRegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50', 'regex:/^[\pL\s\-\']+$/u'],
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'name.string' => 'Your name must be a valid text value.',
            'name.max' => 'Your name may not be greater than 50 characters.',
            'name.regex' => 'Names may only contain letters, spaces, hyphens, and apostrophes.',

            'email.required' => 'An email address is required to create an account.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered. Try logging in instead.',

            'password.required' => 'A secure password is required.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
