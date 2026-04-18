<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        $priceBefore = $this->faker->randomFloat(2, 50, 500);
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'short_description' => $this->faker->text(200),
            'description' => $this->faker->paragraphs(5, true),
            'thumbnail' => 'course_thumb.jpg',
            'attendance_type' => $this->faker->randomElement(['Online', 'Offline', 'Hybrid']),
            'price_before' => $priceBefore,
            'discount_price' => $this->faker->randomFloat(2, 0, 40),
            'level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'language' => 'Arabic',
            'duration_weeks' => $this->faker->numberBetween(4, 12),
            'duration_hours' => $this->faker->numberBetween(20, 100),
            'status' => 'published',
            'category_id' => Category::inRandomOrder()->first()->id ?? Category::factory(),
            'instructor_id' => Instructor::inRandomOrder()->first()->id ?? Instructor::factory(),
            'published_at' => now(),
        ];
    }
}
