<?php

namespace Database\Seeders;

use App\Models\CourseReview;
use App\Models\Enrollment;
use Illuminate\Database\Seeder;

/**
 * CourseReviewSeeder
 * ------------------
 * ينشئ تقييمات فقط للطلاب الذين أكملوا كورساتهم.
 * يستخدم updateOrCreate لمنع التكرار (طالب لا يُقيّم الكورس أكثر من مرة).
 * يضبط review_status و rating بشكل صحيح.
 */
class CourseReviewSeeder extends Seeder
{
    private array $comments = [
        'كورس ممتاز جداً، استفدت منه كثيراً.',
        'محتوى غني ومدرب محترف، أنصح به.',
        'تجربة تعليمية رائعة، سأنضم لكورسات أخرى.',
        'الكورس شرح مستواه ممتاز والأمثلة عملية.',
        'إضافة قيّمة لمسيرتي المهنية.',
        'نظّم وقتي بشكل أفضل بفضل هذا الكورس.',
        'المنهج واضح والمدرب صبور ومفيد.',
        'أفضل استثمار في تعلّمي حتى الآن.',
    ];

    public function run(): void
    {
        $completedEnrollments = Enrollment::where('is_completed', true)
            ->with('course')
            ->get();

        if ($completedEnrollments->isEmpty()) {
            $this->command->warn('لا توجد اشتراكات مكتملة لإنشاء تقييمات.');
            return;
        }

        $count = 0;

        foreach ($completedEnrollments as $enrollment) {
            $course = $enrollment->course;

            if (!$course) {
                continue;
            }

            $contentRating    = rand(3, 5);
            $instructorRating = rand(4, 5);
            $centerRating     = rand(3, 5);
            // rating يُحسب في CourseReview::boot() saving hook تلقائياً
            $avgRating        = round(($contentRating + $instructorRating + $centerRating) / 3, 2);

            CourseReview::updateOrCreate(
                [
                    'course_id'  => $enrollment->course_id,
                    'student_id' => $enrollment->student_id,
                ],
                [
                    'instructor_id'    => $course->instructor_id,
                    'content_rating'   => $contentRating,
                    'instructor_rating'=> $instructorRating,
                    'center_rating'    => $centerRating,
                    'rating'           => $avgRating,
                    'overall_comment'  => $this->comments[array_rand($this->comments)] . ' (' . $course->title . ')',
                    'review_status'    => 'accepted',
                ]
            );

            $count++;
        }

        $this->command->info("✓ CourseReviewSeeder: تم إنشاء {$count} تقييم للكورسات المكتملة.");
    }
}
