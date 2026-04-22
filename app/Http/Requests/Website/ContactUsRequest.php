<?php

namespace App\Http\Requests\Website;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactUsRequest extends FormRequest
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
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', 'unique:contact_us,email'],
            'phone'          => ['required', 'string', 'max:20', 'unique:contact_us,phone'],
            'learning_track' => ['required', 'string', 'max:255'],
            'message'        => ['required', 'string', 'max:500'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'name.required'           => 'The name field is required.',
            'email.required'          => 'The email field is required.',
            'email.email'             => 'Please provide a valid email address.',
            'email.unique'            => 'This email has already been submitted.',
            'phone.required'          => 'The phone field is required.',
            'phone.unique'            => 'This phone number has already been submitted.',
            'learning_track.required' => 'Please select a learning track.',
            'message.required'        => 'The message field is required.',
            'message.max'             => 'The message may not be greater than 500 characters.',
        ];
    }
}
