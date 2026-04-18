<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::inRandomOrder()->first()->id ?? Student::factory(),
            'course_id' => Course::inRandomOrder()->first()->id ?? Course::factory(),
            'order_id' => Order::inRandomOrder()->first()->id ?? Order::factory(),
            'price_paid' => fake()->numberBetween(100, 1000),
            'is_completed' => false,
        ];
    }

    /**
     * حالة خاصة: اشتراك بدون أوردر (مثل الكورسات المجانية أو إضافة يدوية من الأدمن)
     */
    public function manual(): static
    {
        return $this->state(fn(array $attributes) => [
            'order_id' => null,
            'price_paid' => 0,
        ]);
    }
}
