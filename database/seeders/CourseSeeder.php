<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseLearning;
use App\Models\CoursePreview;
use App\Models\Instructor;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // التأكد من وجود بيانات في الجداول المرتبطة أولاً
        $categories = Category::all();
        $instructors = Instructor::all();
        $tags = Tag::all();

        if ($categories->isEmpty() || $instructors->isEmpty()) {
            $this->command->info('من فضلك شغل الـ Seeders بتاعة الـ Category والـ Instructor الأول!');
            return;
        }

        // إنشاء 15 كورس تجريبي
        Course::factory(15)->make()->each(function ($course) use ($categories, $instructors, $tags) {
            // توزيع الكورسات عشوائياً على الأقسام والمدربين الموجودين
            $course->category_id = $categories->random()->id;
            $course->instructor_id = $instructors->random()->id;
            $course->save();

            // ربط الكورس بـ 3 تاجز عشوائية (Many-to-Many)
            if ($tags->isNotEmpty()) {
                $course->tags()->attach(
                    $tags->random(rand(2, 4))->pluck('id')->toArray()
                );
            }
        });

        // لكل كورس موجود، كريت له من 4 لـ 6 نقاط تعلم
        Course::all()->each(function ($course) {
            CourseLearning::factory(rand(4, 6))->create([
                'course_id' => $course->id
            ]);
        });

        Course::all()->each(function ($course) {
            CoursePreview::factory(rand(1, 3))->create([
                'course_id' => $course->id
            ]);
        });
    }
}
    

