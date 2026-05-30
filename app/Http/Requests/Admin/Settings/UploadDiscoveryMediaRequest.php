<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadDiscoveryMediaRequest extends FormRequest
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
            // Determine the operation type: replace or append
            'action' => 'required|in:replace,append',
            'images' => 'required|array|min:1|max:35',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:3072', // Maximum 3MB for the image
        ];
    }
}
