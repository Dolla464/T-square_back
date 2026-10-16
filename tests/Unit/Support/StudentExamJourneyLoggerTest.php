<?php

use App\Support\StudentExamJourneyLogger;
use Illuminate\Support\Facades\Config;

function journeyTestLogPath(): string
{
    return storage_path('logs/test-student-exam-journey.log');
}

function useJourneyTestLogChannel(): void
{
    $path = journeyTestLogPath();

    if (is_file($path)) {
        unlink($path);
    }

    Config::set('logging.channels.student_exam', [
        'driver' => 'single',
        'path' => $path,
        'level' => 'debug',
        'replace_placeholders' => true,
    ]);
}

it('does not write when disabled', function (): void {
    useJourneyTestLogChannel();
    Config::set('exam_journey.enabled', false);

    app(StudentExamJourneyLogger::class)->log('TEST_EVENT');

    expect(is_file(journeyTestLogPath()))->toBeFalse();
});

it('writes structured context when enabled', function (): void {
    useJourneyTestLogChannel();
    Config::set('exam_journey.enabled', true);

    app(StudentExamJourneyLogger::class)->log(
        StudentExamJourneyLogger::EVENT_ATTEMPT_STARTED,
        ['attempt_id' => 42, 'password' => 'secret'],
    );

    $contents = file_get_contents(journeyTestLogPath());

    expect($contents)->toContain('ATTEMPT_STARTED')
        ->and($contents)->toContain('"attempt_id":42')
        ->and($contents)->not->toContain('secret');
});
