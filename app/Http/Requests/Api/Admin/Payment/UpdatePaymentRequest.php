<?php

namespace App\Http\Requests\Api\Admin\Payment;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

            'attendance_type' => ['sometimes', 'string', 'in:online,offline,hybrid'],

            'billing_name' => ['sometimes', 'string', 'max:255'],
            'billing_email' => ['sometimes', 'string', 'email', 'max:255'],
            'billing_phone' => ['sometimes', 'string', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $orderId = $this->route('payment');

            if ($orderId === null) {
                return;
            }

            $order = Order::query()
                ->with('enrollments')
                ->find($orderId);

            if ($order === null) {
                return;
            }

            $currentStatus = $order->status;
            $requestedStatus = $this->input('status', $currentStatus);
            $wasCompleted = $currentStatus === 'completed';
            $enteringCompleted = ! $wasCompleted && $requestedStatus === 'completed';

            if (
                $this->has('attendance_type')
                && in_array($requestedStatus, ['cancelled', 'refunded'], true)
            ) {
                $validator->errors()->add(
                    'attendance_type',
                    'The attendance type field is prohibited when cancelling or refunding an order.'
                );

                return;
            }

            if ($enteringCompleted && ! $this->filled('attendance_type')) {
                $validator->errors()->add(
                    'attendance_type',
                    'The attendance type field is required when completing an order.'
                );

                return;
            }

            $enrollment = $order->enrollments->sortBy('id')->first();

            if ($enteringCompleted && $enrollment === null) {
                $validator->errors()->add(
                    'status',
                    'Cannot complete an order without an associated enrollment.'
                );

                return;
            }

            if ($enteringCompleted && $this->filled('course_id')) {
                $requestedCourseId = (int) $this->input('course_id');

                if ($enrollment !== null && $enrollment->course_id !== $requestedCourseId) {
                    $validator->errors()->add(
                        'course_id',
                        'Cannot change the course while completing an order.'
                    );

                    return;
                }
            }

            if ($this->filled('attendance_type')) {
                if ($enteringCompleted && $enrollment !== null && $enrollment->attendance_type !== null) {
                    if (strtolower((string) $enrollment->attendance_type) !== strtolower((string) $this->input('attendance_type'))) {
                        $validator->errors()->add(
                            'attendance_type',
                            'The enrollment attendance type cannot be changed.'
                        );

                        return;
                    }
                }

                if ($wasCompleted && $requestedStatus === 'completed' && $enrollment !== null && $enrollment->attendance_type !== null) {
                    if (strtolower((string) $enrollment->attendance_type) !== strtolower((string) $this->input('attendance_type'))) {
                        $validator->errors()->add(
                            'attendance_type',
                            'The enrollment attendance type cannot be changed.'
                        );

                        return;
                    }
                }

                if ($enteringCompleted || ($wasCompleted && $requestedStatus === 'completed')) {
                    $course = $this->resolveCourseForValidation($order, $enrollment);

                    if ($course === null) {
                        return;
                    }

                    if (! $course->supportsAttendanceType((string) $this->input('attendance_type'))) {
                        $validator->errors()->add(
                            'attendance_type',
                            'The selected attendance type is not supported by this course.'
                        );
                    }
                }
            }
        });
    }

    private function resolveCourseForValidation(Order $order, ?Enrollment $enrollment): ?Course
    {
        if ($this->filled('course_id')) {
            return Course::query()->find((int) $this->input('course_id'));
        }

        if ($enrollment !== null) {
            return Course::query()->find($enrollment->course_id);
        }

        return null;
    }
}
