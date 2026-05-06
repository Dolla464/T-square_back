<?php

namespace App\Http\Requests\Api\Admin\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],

            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'price_paid' => ['nullable', 'numeric', 'min:0'],

            'status' => ['nullable', 'in:pending,completed,cancelled,refunded'],

            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'string', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

