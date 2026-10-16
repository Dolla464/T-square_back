<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignExamJourneyCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->attributes->has('exam_journey_correlation_id')) {
            $incoming = $request->header('X-Exam-Journey-Correlation-Id');

            $request->attributes->set(
                'exam_journey_correlation_id',
                is_string($incoming) && Str::isUuid($incoming) ? $incoming : (string) Str::uuid(),
            );
        }

        $response = $next($request);

        $correlationId = $request->attributes->get('exam_journey_correlation_id');

        if (is_string($correlationId) && $correlationId !== '') {
            $response->headers->set('X-Exam-Journey-Correlation-Id', $correlationId);
        }

        return $response;
    }
}
