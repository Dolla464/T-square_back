<?php

use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function courseInstructorIdFor(Course $course, Instructor $instructor): int
{
    return CourseInstructor::firstOrCreate(
        [
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
        ],
        ['sort_order' => 0]
    )->id;
}

function groupPayloadWithInstructor(Course $course, Instructor $instructor, array $overrides = []): array
{
    return array_merge([
        'course_instructor_id' => courseInstructorIdFor($course, $instructor),
    ], $overrides);
}

function actingAsInstructor(Instructor $instructor): void
{
    $user = $instructor->user;
    $user->assignRole('instructor');
    Sanctum::actingAs($user, ['*']);
}
