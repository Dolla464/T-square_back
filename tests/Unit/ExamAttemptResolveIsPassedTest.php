<?php

use App\Models\ExamAttempt;

it('resolves is_passed from attempt status', function (string $status, ?bool $expected) {
    $attempt = new ExamAttempt;
    $attempt->status = $status;

    expect($attempt->resolveIsPassed())->toBe($expected);
})->with([
    ['passed', true],
    ['failed', false],
    ['timed_out', false],
    ['awaiting_grading', null],
    ['ongoing', null],
]);
