<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->instructor = Instructor::factory()->create();

    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'duration_weeks' => 4,
    ]);
});

function createEnrollmentForGuardTest(
    Course $course,
    Instructor $instructor,
    ?LearningGroup $group = null,
    bool $isCompleted = false,
): Enrollment {
    $student = Student::factory()->create();

    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => 500,
        'status' => 'completed',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    return Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $group?->id,
        'price_paid' => 500,
        'is_completed' => $isCompleted,
        'completed_at' => $isCompleted ? now() : null,
    ]);
}

function createGroupForGuardTest(
    Course $course,
    Instructor $instructor,
    string $status = 'active',
): LearningGroup {
    return LearningGroup::create([
        'group_name' => 'Guard Test Batch',
        'course_id' => $course->id,
        'course_instructor_id' => courseInstructorIdFor($course, $instructor),
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => $status,
        'enrolled_students' => 0,
    ]);
}

it('rejects completion when the learning group is active', function (): void {
    $group = createGroupForGuardTest($this->course, $this->instructor, 'active');
    $enrollment = createEnrollmentForGuardTest($this->course, $this->instructor, $group);

    expect(fn () => $enrollment->markAsCompleted())
        ->toThrow(ValidationException::class);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    expect($enrollment->completed_at)->toBeNull();
});

it('allows completion when the learning group is completed', function (): void {
    $group = createGroupForGuardTest($this->course, $this->instructor, 'completed');
    $enrollment = createEnrollmentForGuardTest($this->course, $this->instructor, $group);

    $enrollment->markAsCompleted();
    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeTrue();
    expect($enrollment->completed_at)->not->toBeNull();
});

it('rejects completion when the learning group is cancelled', function (): void {
    $group = createGroupForGuardTest($this->course, $this->instructor, 'cancelled');
    $enrollment = createEnrollmentForGuardTest($this->course, $this->instructor, $group);

    expect(fn () => $enrollment->markAsCompleted())
        ->toThrow(ValidationException::class);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
});

it('rejects completion when group_id is null', function (): void {
    $enrollment = createEnrollmentForGuardTest($this->course, $this->instructor, null);

    try {
        $enrollment->markAsCompleted();
        expect(false)->toBeTrue('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('is_completed');
    }

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    expect($enrollment->group_id)->toBeNull();
});

it('does not throw when loading a legacy completed enrollment on an active group', function (): void {
    $group = createGroupForGuardTest($this->course, $this->instructor, 'active');
    $enrollment = createEnrollmentForGuardTest($this->course, $this->instructor, $group, true);

    $loaded = Enrollment::query()->findOrFail($enrollment->id);

    expect($loaded->is_completed)->toBeTrue();
    expect($loaded->learningGroup->status)->toBe('active');

    $loaded->markAsCompleted();
    $loaded->refresh();

    expect($loaded->is_completed)->toBeTrue();
});

it('allows setting is_completed back to false without invoking the guard', function (): void {
    $group = createGroupForGuardTest($this->course, $this->instructor, 'completed');
    $enrollment = createEnrollmentForGuardTest($this->course, $this->instructor, $group, true);

    $enrollment->update([
        'is_completed' => false,
        'completed_at' => null,
    ]);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    expect($enrollment->completed_at)->toBeNull();
});
