<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AdminNewEnrollmentNotification;
use App\Notifications\InstructorGradingRequiredNotification;
use App\Notifications\StudentEnrolledNotification;
use App\Notifications\StudentExamAttemptStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('includes course_title in student enrollment notification payload', function (): void {
    $course = Course::factory()->create(['title' => 'Intro to PHP']);
    $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
    $user = User::factory()->create();

    $notification = new StudentEnrolledNotification($course, $enrollment);
    $payload = $notification->toDatabase($user);

    expect($payload)
        ->toHaveKey('course_title', 'Intro to PHP')
        ->and($payload['type'])->toBe('enrollment');
});

it('includes course_title and student_name in admin enrollment notification payload', function (): void {
    $course = Course::factory()->create(['title' => 'Advanced Laravel']);
    $student = Student::factory()->create(['full_name' => 'Ahmed Ali']);
    $enrollment = Enrollment::factory()->create([
        'course_id' => $course->id,
        'student_id' => $student->id,
    ]);
    $user = User::factory()->create();

    $notification = new AdminNewEnrollmentNotification($course, $student, $enrollment);
    $payload = $notification->toDatabase($user);

    expect($payload)
        ->toHaveKey('course_title', 'Advanced Laravel')
        ->toHaveKey('student_name', 'Ahmed Ali')
        ->and($payload['type'])->toBe('admin_enrollment');
});

it('includes course_title and exam_title in student exam attempt result notification payload', function (): void {
    $course = Course::factory()->create(['title' => 'Intro to PHP']);
    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'title' => 'Final Quiz',
    ]);
    $student = Student::factory()->create();
    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'status' => 'passed',
        'score' => 85,
    ]);
    $user = User::factory()->create();

    $notification = new StudentExamAttemptStatusNotification($attempt);
    $payload = $notification->toDatabase($user);

    expect($payload)
        ->toHaveKey('course_title', 'Intro to PHP')
        ->toHaveKey('exam_title', 'Final Quiz')
        ->toHaveKey('course_id', $course->id)
        ->and($payload['type'])->toBe('exam_result')
        ->and($payload['message'])->toContain('Final Quiz')
        ->and($payload['message'])->toContain('Intro to PHP');
});

it('includes student_name and exam_title in instructor grading required notification payload', function (): void {
    $exam = Exam::factory()->create(['title' => 'Midterm Exam']);
    $student = Student::factory()->create(['full_name' => 'Sara Hassan']);
    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'status' => 'awaiting_grading',
    ]);
    $user = User::factory()->create();

    $notification = new InstructorGradingRequiredNotification($attempt, 'Group A');
    $payload = $notification->toDatabase($user);

    expect($payload)
        ->toHaveKey('student_name', 'Sara Hassan')
        ->toHaveKey('exam_title', 'Midterm Exam')
        ->and($payload['type'])->toBe('grading_required');
});

it('enriches legacy exam result notifications with course and exam titles from exam_id', function (): void {
    $course = Course::factory()->create(['title' => 'Legacy Course']);
    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'title' => 'Legacy Exam',
    ]);
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\StudentExamAttemptStatusNotification',
        'data' => [
            'type' => 'exam_result',
            'title' => 'Exam Attempt Result',
            'message' => 'You failed your exam attempt.',
            'exam_id' => $exam->id,
            'attempt_id' => 99,
            'status' => 'failed',
            'score' => 4,
            'icon' => 'x-circle',
        ],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/notifications?page=1&per_page=30');

    $response->assertOk();

    expect($response->json('data.0.course_title'))->toBe('Legacy Course')
        ->and($response->json('data.0.exam_title'))->toBe('Legacy Exam')
        ->and($response->json('data.0.message'))->toContain('Legacy Exam')
        ->and($response->json('data.0.message'))->toContain('Legacy Course');
});

it('exposes student_name and exam_title in notification list api response', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\InstructorGradingRequiredNotification',
        'data' => [
            'type' => 'grading_required',
            'title' => 'Exam Grading Required',
            'message' => 'Essay answers are awaiting your grading.',
            'student_name' => 'Sara Hassan',
            'exam_title' => 'Midterm Exam',
            'status' => 'awaiting_grading',
            'icon' => 'pencil-square',
        ],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/notifications?page=1&per_page=30');

    $response->assertOk();

    expect($response->json('data.0.student_name'))->toBe('Sara Hassan')
        ->and($response->json('data.0.exam_title'))->toBe('Midterm Exam');
});
