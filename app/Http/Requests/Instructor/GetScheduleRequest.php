<?php

namespace App\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class GetScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && $user->instructor()->exists();
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date'        => 'The date must be a valid date.',
            'date.date_format' => 'The date must be in Y-m-d format (e.g. 2026-06-28).',
        ];
    }
}
