<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('supports online attendance type', function (): void {
    $course = new Course(['attendance_types' => ['online']]);

    expect($course->supportsAttendanceType('online'))->toBeTrue();
});

it('supports offline attendance type', function (): void {
    $course = new Course(['attendance_types' => ['offline']]);

    expect($course->supportsAttendanceType('offline'))->toBeTrue();
});

it('supports hybrid attendance type', function (): void {
    $course = new Course(['attendance_types' => ['hybrid']]);

    expect($course->supportsAttendanceType('hybrid'))->toBeTrue();
});

it('returns false for unsupported attendance type', function (): void {
    $course = new Course(['attendance_types' => ['online']]);

    expect($course->supportsAttendanceType('hybrid'))->toBeFalse();
});

it('normalizes attendance type input case when checking support', function (): void {
    $course = new Course(['attendance_types' => ['online']]);

    expect($course->supportsAttendanceType('ONLINE'))->toBeTrue();
});

it('normalizes attendance type input whitespace when checking support', function (): void {
    $course = new Course(['attendance_types' => ['online']]);

    expect($course->supportsAttendanceType(' online '))->toBeTrue();
});

it('returns false when attendance types array is empty', function (): void {
    $course = new Course(['attendance_types' => []]);

    expect($course->supportsAttendanceType('online'))->toBeFalse();
});

it('returns false when attendance types is null or unset', function (): void {
    $course = new Course;

    expect($course->supportsAttendanceType('online'))->toBeFalse();
});

it('requires a learning group for offline attendance type', function (): void {
    $course = new Course;

    expect($course->requiresLearningGroup('offline'))->toBeTrue();
});

it('requires a learning group for hybrid attendance type', function (): void {
    $course = new Course;

    expect($course->requiresLearningGroup('hybrid'))->toBeTrue();
});

it('does not require a learning group for online attendance type', function (): void {
    $course = new Course;

    expect($course->requiresLearningGroup('online'))->toBeFalse();
});

it('returns false for unknown or empty attendance type when checking learning group requirement', function (): void {
    $course = new Course;

    expect($course->requiresLearningGroup('unknown'))->toBeFalse()
        ->and($course->requiresLearningGroup(''))->toBeFalse();
});

it('casts attendance_types as an array from the database', function (): void {
    $course = Course::factory()->create([
        'attendance_types' => ['online', 'offline'],
    ]);

    $freshCourse = $course->fresh();

    expect($freshCourse->attendance_types)
        ->toBeArray()
        ->toBe(['online', 'offline']);
});

it('uses attendance_types column and no longer has attendance_type column', function (): void {
    expect(Schema::hasColumn('courses', 'attendance_types'))->toBeTrue()
        ->and(Schema::hasColumn('courses', 'attendance_type'))->toBeFalse();
});

it('creates a course through the factory with attendance_types', function (): void {
    $course = Course::factory()->create();

    expect($course->attendance_types)
        ->toBeArray()
        ->not->toBeEmpty();

    foreach ($course->attendance_types as $type) {
        expect($type)->toBeIn(Course::ATTENDANCE_TYPES);
    }
});
