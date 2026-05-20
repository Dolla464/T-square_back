<?php

namespace App\Services\User;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    /**
     * @return array{enrollment: Enrollment, order: Order|null}
     */
    public function enroll(Student $student, array $payload): array
    {
        $course = Course::query()->find($payload['course_id']);

        if (! $course) {
            throw (new ModelNotFoundException)->setModel(Course::class, [$payload['course_id']]);
        }

        $isPaidCourse = ! (bool) $course->is_free && (float) $course->price > 0;

        // prepare whatsapp
        $studentName = $student->user->name;
        $whatsappNumber = Setting::query()->where('key', 'whatsapp')->value('value');
        $message = "أهلاً، أنا {$studentName}، لقد قمت بالتسجيل للتو في كورس '{$course->title}' وأرغب في استكمال التفاصيل.";
        $whatsappLink = "https://wa.me/{$whatsappNumber}?text=" . urlencode($message);

        return DB::transaction(function () use ($student, $payload, $course, $isPaidCourse, $whatsappLink) {

            $existingEnrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existingEnrollment) {
                throw ValidationException::withMessages([

                    // 'course_id' => ['You are already enrolled in this course.'],
                    'course_id' => [
                        'message' => 'You are already enrolled in this course.',
                        'error_code' => 'ALREADY_ENROLLED',
                        'details' => [
                            'enrollment_id' => $existingEnrollment->id,
                            'enrolled_at'   => $existingEnrollment->created_class ?? $existingEnrollment->created_at->toDateTimeString(),
                            'is_completed'  => (bool) $existingEnrollment->is_completed,
                            'course_title'  => $course->title, // لو حابب تبعت اسم الكورس زيادة تأكيد
                            'price_paid'    => (float) $existingEnrollment->price_paid,
                        ]
                    ],
                ]);
            }
            if (! $isPaidCourse) {
                $enrollment = Enrollment::query()->create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'order_id' => null,
                    'price_paid' => 0,
                    'is_completed' => false,
                    'completed_at' => null,
                ]);

                return [
                    'enrollment' => $enrollment,
                    'order' => null,
                    'whatsapp_link' => $whatsappLink,
                ];
            }

            $order = Order::query()->create([
                'student_id' => $student->id,
                'total_amount' => (float) $course->price,
                'status' => 'pending',
                'billing_name' => $payload['billing_name'],
                'billing_email' => $payload['billing_email'],
                'billing_phone' => $payload['billing_phone'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $enrollment = Enrollment::query()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'order_id' => $order->id,
                'price_paid' => (float) $course->price,
                'is_completed' => false,
                'completed_at' => null,
            ]);

            return [
                'enrollment' => $enrollment,
                'order' => $order,
                'whatsapp_link' => $whatsappLink,
            ];
        });
    }
}
