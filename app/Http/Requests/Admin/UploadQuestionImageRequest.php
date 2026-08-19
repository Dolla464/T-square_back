<?php

namespace App\Http\Requests\Admin;

use App\Rules\NoDoubleExtension;
use Illuminate\Foundation\Http\FormRequest;

class UploadQuestionImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:max=4096,4096', new NoDoubleExtension],
        ];
    }
}
