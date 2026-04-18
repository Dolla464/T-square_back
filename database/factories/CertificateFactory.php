<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'certificate_url' => 'certificates/sample_' . $this->faker->uuid . '.pdf',
            'certificate_num' => 'TSQ-' . strtoupper($this->faker->bothify('??###-####')),
            'issued_at' => now(),
        ];
    }
}
