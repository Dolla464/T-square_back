<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $priceBefore = $this->faker->randomFloat(2, 50, 500);
        $discount    = $this->faker->randomFloat(2, 0, 40);

        return [
            'title'             => $this->faker->sentence(4),
            // slug يُولَّد تلقائياً بواسطة EloquentSluggable — لا تُضف هنا
            'short_description' => $this->faker->text(200),
            'description'       => $this->faker->paragraphs(5, true),
            'thumbnail'         => 'course_thumb.jpg',
            'attendance_type'   => $this->faker->randomElement(['Online', 'Offline', 'Hybrid']),
            'price_before'      => $priceBefore,
            'discount_price'    => $discount,
            // price يُحسب تلقائياً في Course::booted() — لا داعي لضبطه هنا
            'level'             => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'language'          => $this->faker->randomElement(['Arabic', 'English']),
            'duration_weeks'    => $this->faker->numberBetween(4, 12),
            'duration_hours'    => $this->faker->numberBetween(20, 100),
            'status'            => 'published',
            'is_featured'       => $this->faker->boolean(20),
            'is_free'           => false,
            'category_id'       => Category::inRandomOrder()->first()?->id ?? Category::factory(),
            'instructor_id'     => Instructor::inRandomOrder()->first()?->id ?? Instructor::factory(),
            'published_at'      => now(),
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'is_free'        => true,
            'price_before'   => 0,
            'discount_price' => 0,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft', 'published_at' => null]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Course $course) {
            if ($course->instructor_id) {
                CourseInstructor::firstOrCreate(
                    [
                        'course_id' => $course->id,
                        'instructor_id' => $course->instructor_id,
                    ],
                    ['sort_order' => 0]
                );
            }
        });
    }
}
