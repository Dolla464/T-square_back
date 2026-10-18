<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchGroupAttendanceCardsRequest;
use App\Models\LearningGroup;
use App\Services\Attendance\GroupAttendanceCardBulkException;
use App\Services\Attendance\GroupAttendanceCardBulkService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Admin: Learning group attendance card bulk issuance
 */
class AdminGroupAttendanceCardBulkController extends Controller
{
    public function __construct(
        private GroupAttendanceCardBulkService $bulkService,
    ) {}

    /**
     * POST /api/admin/learning-groups/{learningGroup}/attendance-cards/batch
     */
    public function processBatch(
        BatchGroupAttendanceCardsRequest $request,
        LearningGroup $learningGroup,
    ): JsonResponse {
        try {
            $payload = $this->bulkService->processBatch(
                $learningGroup,
                $request->user(),
                $request->validated(),
            );
        } catch (GroupAttendanceCardBulkException $e) {
            return $this->structuredErrorResponse(
                error: $e->getMessage(),
                code: $e->errorCode,
                httpCode: $e->httpStatus,
            );
        }

        return $this->successResponse($payload, 'Bulk attendance card batch processed successfully');
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/attendance-cards/batch/{clientOperationId}
     */
    public function showStatus(LearningGroup $learningGroup, string $clientOperationId): JsonResponse
    {
        try {
            $payload = $this->bulkService->operationStatus($learningGroup, $clientOperationId);
        } catch (GroupAttendanceCardBulkException $e) {
            return $this->structuredErrorResponse(
                error: $e->getMessage(),
                code: $e->errorCode,
                httpCode: $e->httpStatus,
            );
        }

        return $this->successResponse($payload, 'Bulk attendance card operation status retrieved successfully');
    }
}
