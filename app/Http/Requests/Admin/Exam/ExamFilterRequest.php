<?php

namespace App\Http\Requests\Admin\Exam;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExamFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'     => 'nullable|string|max:255',
            'status'     => 'nullable|in:0,1',
            'date_range' => 'nullable|in:last_week,last_month,last_year',
        ];
    }
}
