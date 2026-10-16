<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReissueStudentAttendanceCardRequest;
use App\Http\Requests\Admin\RevokeStudentAttendanceCardRequest;
use App\Http\Resources\Admin\AdminStudentAttendanceCardResource;
use App\Models\Student;
use App\Services\Attendance\DTO\IssuedStudentAttendanceCard;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * @tags Admin: Student attendance cards
 */
class AdminStudentAttendanceCardController extends Controller
{
    private const INACTIVE_WARNING_CODE = 'STUDENT_INACTIVE';

    private const INACTIVE_WARNING_MESSAGE = 'Automatic QR attendance scanning is blocked while this student\'s status is inactive.';

    public function __construct(
        private StudentAttendanceCardLifecycleService $lifecycleService,
    ) {}

    /**
     * GET /api/admin/students/{student}/attendance-card
     */
    public function show(Student $student): JsonResponse
    {
        $card = $this->lifecycleService->findActiveCardForStudent($student);

        return $this->successResponse([
            'has_active_card' => $card !== null,
            'card' => $card !== null ? new AdminStudentAttendanceCardResource($card) : null,
        ], 'Student attendance card status retrieved successfully');
    }

    /**
     * POST /api/admin/students/{student}/attendance-card
     */
    public function issue(Request $request, Student $student): JsonResponse
    {
        try {
            $issued = $this->lifecycleService->issue($request->user(), $student);
        } catch (AuthorizationException $e) {
            return $this->structuredErrorResponse(
                error: 'Unauthorized access',
                code: 'FORBIDDEN',
                httpCode: 403,
                message: $e->getMessage(),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Student already has an active attendance card.') {
                return $this->structuredErrorResponse(
                    error: $e->getMessage(),
                    code: 'CARD_ALREADY_ACTIVE',
                    httpCode: 422,
                );
            }

            throw $e;
        }

        return $this->successResponse(
            $this->issuedPayload($student, $issued),
            'Student attendance card issued successfully',
            201,
        );
    }

    /**
     * POST /api/admin/students/{student}/attendance-card/reissue
     */
    public function reissue(ReissueStudentAttendanceCardRequest $request, Student $student): JsonResponse
    {
        try {
            $issued = $this->lifecycleService->reissue(
                $request->user(),
                $student,
                $request->validated('revocation_reason'),
            );
        } catch (AuthorizationException $e) {
            return $this->structuredErrorResponse(
                error: 'Unauthorized access',
                code: 'FORBIDDEN',
                httpCode: 403,
                message: $e->getMessage(),
            );
        }

        return $this->successResponse(
            $this->issuedPayload($student, $issued),
            'Student attendance card reissued successfully',
        );
    }

    /**
     * POST /api/admin/students/{student}/attendance-card/revoke
     */
    public function revoke(RevokeStudentAttendanceCardRequest $request, Student $student): JsonResponse
    {
        $activeCard = $this->lifecycleService->findActiveCardForStudent($student);

        if ($activeCard === null) {
            return $this->structuredErrorResponse(
                error: 'No active attendance card exists for this student.',
                code: 'NO_ACTIVE_CARD',
                httpCode: 422,
            );
        }

        try {
            $revoked = $this->lifecycleService->revoke(
                $request->user(),
                $activeCard,
                $request->validated('revocation_reason'),
            );
        } catch (AuthorizationException $e) {
            return $this->structuredErrorResponse(
                error: 'Unauthorized access',
                code: 'FORBIDDEN',
                httpCode: 403,
                message: $e->getMessage(),
            );
        }

        return $this->successResponse([
            'card' => new AdminStudentAttendanceCardResource($revoked),
        ], 'Student attendance card revoked successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function issuedPayload(Student $student, IssuedStudentAttendanceCard $issued): array
    {
        $payload = [
            'card' => new AdminStudentAttendanceCardResource($issued->card),
            'plain_text_token' => $issued->plainTextToken,
        ];

        $warnings = $this->inactiveStudentWarnings($student);
        if ($warnings !== []) {
            $payload['warnings'] = $warnings;
        }

        return $payload;
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    private function inactiveStudentWarnings(Student $student): array
    {
        $status = $student->fresh()->status ?? $student->status;

        if ($status !== 'inactive') {
            return [];
        }

        return [
            [
                'code' => self::INACTIVE_WARNING_CODE,
                'message' => self::INACTIVE_WARNING_MESSAGE,
            ],
        ];
    }
}
