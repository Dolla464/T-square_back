<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CoursePreview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoursePreview>
 */
class CoursePreviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::inRandomOrder()->first()->id ?? Course::factory(),
            'title' => 'Intro: ' . $this->faker->sentence(3),
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', // مثال
            'video_provider' => 'youtube',
            'duration_seconds' => $this->faker->numberBetween(60, 300),
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
