<?php

namespace App\Support;

use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StudentExamJourneyLogger
{
    public const EVENT_ATTEMPT_STARTED = 'ATTEMPT_STARTED';

    public const EVENT_ATTEMPT_RESUMED = 'ATTEMPT_RESUMED';

    public const EVENT_ATTEMPT_COMPLETED = 'ATTEMPT_COMPLETED';

    public const EVENT_ACCESS_DENIED = 'ACCESS_DENIED';

    public const EVENT_AUTO_COMPLETE_SCHEDULER = 'AUTO_COMPLETE_SCHEDULER';

    public const EVENT_CLIENT_EVENTS_BATCH = 'CLIENT_EVENTS_BATCH';

    public const EVENT_UNEXPECTED_ERROR = 'UNEXPECTED_ERROR';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'answer_text',
        'choice_text',
        'authorization',
        'cookie',
        'secret',
    ];

    public function enabled(): bool
    {
        return (bool) config('exam_journey.enabled', false);
    }

    public function clientEventsEnabled(): bool
    {
        return $this->enabled() && (bool) config('exam_journey.client_events_enabled', true);
    }

    public function correlationId(?Request $request = null): ?string
    {
        $request ??= request();

        if (! $request) {
            return null;
        }

        $existing = $request->attributes->get('exam_journey_correlation_id');

        return is_string($existing) && $existing !== '' ? $existing : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, array $context = [], string $source = 'server', string $level = 'info'): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $payload = $this->sanitize(array_merge($this->baseContext($source), [
                'event' => $event,
            ], $context));

            Log::channel('student_exam')->log($level, 'student_exam_journey', $payload);
        } catch (Throwable) {
            // Never break exam flows because logging failed.
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function logFromAttempt(string $event, ExamAttempt $attempt, array $extra = [], string $source = 'server'): void
    {
        $attempt->loadMissing('exam');

        $this->log($event, array_merge([
            'attempt_id' => $attempt->id,
            'exam_id' => $attempt->exam_id,
            'student_id' => $attempt->student_id,
            'attempt_status' => $attempt->status,
            'course_id' => $attempt->exam?->course_id,
        ], $extra), $source);
    }

    public function logAccessDenied(
        int $httpStatus,
        string $message,
        ?ExamAttempt $attempt = null,
        ?int $studentId = null,
        ?int $examId = null,
        array $meta = [],
    ): void {
        if (! $this->enabled()) {
            return;
        }

        try {
            if (request()) {
                request()->attributes->set('exam_journey_deny_logged', true);
            }
        } catch (Throwable) {
            //
        }

        $context = array_merge([
            'http_status' => $httpStatus,
            'deny_message' => Str::limit($message, 500, ''),
        ], $meta);

        if ($attempt !== null) {
            $this->logFromAttempt(self::EVENT_ACCESS_DENIED, $attempt, $context);

            return;
        }

        $context['student_id'] = $studentId;
        $context['exam_id'] = $examId;

        $this->log(self::EVENT_ACCESS_DENIED, $context);
    }

    public function logUnexpected(Throwable $throwable): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->log(self::EVENT_UNEXPECTED_ERROR, [
            'exception_class' => $throwable::class,
            'exception_message' => Str::limit($throwable->getMessage(), 500, ''),
        ], 'server', 'error');
    }

    /**
     * @return array<string, mixed>
     */
    private function baseContext(string $source): array
    {
        $request = request();

        return array_filter([
            'correlation_id' => $this->correlationId($request),
            'source' => $source,
            'request_method' => $request?->method(),
            'request_path' => $request?->path(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $maxString = (int) config('exam_journey.max_meta_string_length', 256);
        $maxKeys = (int) config('exam_journey.max_meta_keys', 10);

        $sanitized = [];

        foreach ($context as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $lowerKey = strtolower($key);

            if (in_array($lowerKey, self::SENSITIVE_KEYS, true)) {
                continue;
            }

            if (str_contains($lowerKey, 'password') || str_contains($lowerKey, 'token')) {
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($value, $maxString, $maxKeys, 0);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value, int $maxString, int $maxKeys, int $depth): mixed
    {
        if ($depth > 3) {
            return '[truncated]';
        }

        if (is_string($value)) {
            return Str::limit($value, $maxString, '…');
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        $result = [];
        $count = 0;

        foreach ($value as $k => $v) {
            if ($count >= $maxKeys) {
                $result['…'] = 'truncated';

                break;
            }

            if (! is_string($k) && ! is_int($k)) {
                continue;
            }

            $key = (string) $k;

            if (in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $result[$key] = $this->sanitizeValue($v, $maxString, $maxKeys, $depth + 1);
            $count++;
        }

        return $result;
    }
}
