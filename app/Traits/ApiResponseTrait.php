<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Success Response
     */
    public function successResponse($data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error Response
     */
    public function errorResponse(?string $message, int $code, $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors, // مخصص لتفاصيل الـ Validation مثلاً
        ], $code);
    }

    public function structuredErrorResponse(
        string $error,
        string $code,
        int $httpCode,
        ?string $message = null,
    ): JsonResponse {
        $message ??= $error;

        return response()->json([
            'status' => 'error',
            'error' => $error,
            'message' => $message,
            'code' => $code,
        ], $httpCode);
    }

    public function authorizationResultResponse(\App\DTO\AuthorizationResult $result): JsonResponse
    {
        return $this->structuredErrorResponse(
            error: $result->getMessage() ?? 'Forbidden',
            code: $result->getCode(),
            httpCode: $result->getStatusCode(),
            message: $result->getMessage(),
        );
    }

    /**
     * Pagination Response
     */
    public function paginateResponse($paginatedData, ?string $message = null): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $paginatedData->items(),
            'pagination' => [
                'total' => $paginatedData->total(),
                'count' => $paginatedData->count(),
                'per_page' => $paginatedData->perPage(),
                'current_page' => $paginatedData->currentPage(),
                'total_pages' => $paginatedData->lastPage(),
            ],
        ], 200);
    }
}
