<?php

use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports assigned instructors from loaded relation without extra query', function (): void {
    $course = Course::factory()->create();
    $first = Instructor::factory()->create();
    $second = Instructor::factory()->create();

    $course->instructors()->sync([
        $first->id => ['sort_order' => 0],
        $second->id => ['sort_order' => 1],
    ]);

    $course->load('instructors');

    expect($course->hasInstructor($first->id))->toBeTrue()
        ->and($course->hasInstructor($second->id))->toBeTrue()
        ->and($course->hasInstructor(999999))->toBeFalse();
});

it('syncs ordered instructor ids on the pivot', function (): void {
    $course = Course::factory()->create();
    $instructors = Instructor::factory()->count(3)->create();

    app(\App\Support\CourseInstructorSync::class)->sync($course, [
        $instructors[2]->id,
        $instructors[0]->id,
        $instructors[1]->id,
    ]);

    $rows = CourseInstructor::query()
        ->where('course_id', $course->id)
        ->orderBy('sort_order')
        ->get();

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->instructor_id)->toBe($instructors[2]->id)
        ->and($rows[1]->instructor_id)->toBe($instructors[0]->id)
        ->and($rows[2]->instructor_id)->toBe($instructors[1]->id);
});

it('blocks removing an instructor assigned to a learning group', function (): void {
    $course = Course::factory()->create();
    $primary = Instructor::factory()->create();
    $secondary = Instructor::factory()->create();

    app(\App\Support\CourseInstructorSync::class)->sync($course, [
        $primary->id,
        $secondary->id,
    ]);

    $secondaryPivotId = CourseInstructor::query()
        ->where('course_id', $course->id)
        ->where('instructor_id', $secondary->id)
        ->value('id');

    \App\Models\LearningGroup::factory()->create([
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
    ]);

    app(\App\Support\CourseInstructorSync::class)->sync($course, [
        $primary->id,
    ]);
})->throws(\Illuminate\Validation\ValidationException::class);
