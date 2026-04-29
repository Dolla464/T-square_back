<?php

namespace App\Http\Requests\Api\Student;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->student()->exists();
    }

    public function rules(): array
    {
        return [
            'course_id'      => ['required', 'integer', 'exists:courses,id'],

            // Required only when course is paid (validated in withValidator()).
            'billing_name'   => ['nullable', 'string', 'max:255'],
            'billing_email'  => ['nullable', 'string', 'email', 'max:255'],
            'billing_phone'  => ['nullable', 'string', 'max:20'],

            'notes'          => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $courseId = (int) $this->input('course_id');
            $course = Course::query()
                ->select(['id', 'is_free', 'price'])
                ->find($courseId);

            if (! $course) {
                return;
            }

            $isPaidCourse = ! (bool) $course->is_free && (float) $course->price > 0;

            if (! $isPaidCourse) {
                return;
            }

            foreach (['billing_name', 'billing_email', 'billing_phone'] as $field) {
                if (! filled($this->input($field))) {
                    $validator->errors()->add($field, "The {$field} field is required for paid courses.");
                }
            }
        });
    }
}

