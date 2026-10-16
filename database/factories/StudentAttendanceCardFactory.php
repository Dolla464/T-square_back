<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory stores hash-only placeholders — never a real scannable card secret.
 *
 * @extends Factory<StudentAttendanceCard>
 */
class StudentAttendanceCardFactory extends Factory
{
    protected $model = StudentAttendanceCard::class;

    public function definition(): array
    {
        $placeholder = 'factory_placeholder_'.fake()->unique()->uuid();

        return [
            'student_id' => Student::factory(),
            'token_hash' => StudentAttendanceCardLifecycleService::hashPlainToken($placeholder),
            'token_suffix' => mb_substr($placeholder, -4),
            'issued_at' => now(),
            'revoked_at' => null,
            'issued_by_user_id' => User::factory(),
            'revoked_by_user_id' => null,
            'revocation_reason' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
            'revoked_by_user_id' => User::factory(),
            'revocation_reason' => 'Revoked in factory state.',
        ]);
    }
}
