<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'sort_order' => 0,
            'is_active' => true,
            'video_source_type' => Lesson::VIDEO_SOURCE_GOOGLE_DRIVE,
            'google_drive_file_id' => '1abcDEF_test_file_id',
        ];
    }
}
