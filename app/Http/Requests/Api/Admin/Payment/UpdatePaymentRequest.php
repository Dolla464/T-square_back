<?php

namespace App\Http\Requests\Api\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],

            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'price_paid' => ['sometimes', 'numeric', 'min:0'],

            'status' => ['sometimes', 'in:pending,completed,cancelled,refunded'],

            'billing_name' => ['sometimes', 'string', 'max:255'],
            'billing_email' => ['sometimes', 'string', 'email', 'max:255'],
            'billing_phone' => ['sometimes', 'string', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
