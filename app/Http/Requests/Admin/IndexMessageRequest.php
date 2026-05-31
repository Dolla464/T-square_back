<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_filter' => ['sometimes', 'nullable', 'string', 'in:last_week,last_month,last_3_months'],
            'per_page'    => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
