<?php

namespace App\Http\Requests\Api\Admin\Payment;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id'    => ['required', 'integer', 'exists:students,id'],
            'course_id'     => ['required', 'integer', 'exists:courses,id'],
            'billing_name'  => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'string', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:20'],
            'notes'         => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $studentId = (int) $this->input('student_id');
            $courseId  = (int) $this->input('course_id');

            $alreadyEnrolled = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('course_id', $courseId)
                ->exists();

            if ($alreadyEnrolled) {
                $validator->errors()->add(
                    'course_id',
                    'This student is already enrolled in the selected course.'
                );
            }
        });
    }
}
