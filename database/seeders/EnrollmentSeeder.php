<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * EnrollmentSeeder
 * ----------------
 * يُسجّل الطلاب في الكورسات مع:
 *  - ربط كل اشتراك بـ Order
 *  - ربط بعض الاشتراكات بـ LearningGroup
 *  - تمييز نسبة من الاشتراكات كـ "مكتملة" لتمكين الشهادات والتقييمات
 */
class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::all();
        $courses  = Course::all();
        $groups   = LearningGroup::all();

        if ($students->isEmpty() || $courses->isEmpty()) {
            $this->command->warn('لا يوجد طلاب أو كورسات — شغّل StudentSeeder و CourseSeeder أولاً.');
            return;
        }

        $enrollmentsCreated = 0;

        $students->each(function (Student $student) use ($courses, $groups, &$enrollmentsCreated) {
            // كل طالب يُسجَّل في 3 إلى 6 كورسات
            $selectedCourses = $courses->random(min(rand(3, 6), $courses->count()));

            foreach ($selectedCourses as $course) {
                // تجنب التكرار
                $exists = Enrollment::where('student_id', $student->id)
                    ->where('course_id', $course->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // إنشاء Order للطالب لهذا الكورس
                $isPaid     = $course->price > 0;
                $orderStatus = $isPaid ? 'completed' : 'completed';

                $order = Order::create([
                    'student_id'    => $student->id,
                    'total_amount'  => $course->price ?? 0,
                    'status'        => $orderStatus,
                    'billing_name'  => $student->full_name,
                    'billing_email' => $student->user->email,
                    'billing_phone' => $student->phone ?? '01000000000',
                    'created_at'    => Carbon::now()->subDays(rand(10, 180)),
                ]);

                // تحديد مجموعة تعلم للكورس إن وُجدت
                $group = $groups->where('course_id', $course->id)->random(1)->first();

                // 30% من الاشتراكات مكتملة
                $isCompleted  = rand(1, 10) <= 3;
                $completedAt  = $isCompleted
                    ? Carbon::now()->subDays(rand(1, 60))
                    : null;

                Enrollment::create([
                    'student_id'   => $student->id,
                    'course_id'    => $course->id,
                    'order_id'     => $order->id,
                    'group_id'     => $group?->id,
                    'price_paid'   => $course->price ?? 0,
                    'is_completed' => $isCompleted,
                    'completed_at' => $completedAt,
                ]);

                $enrollmentsCreated++;
            }
        });

        $this->command->info("✓ EnrollmentSeeder: تم إنشاء {$enrollmentsCreated} اشتراكاً.");
    }
}
