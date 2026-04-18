<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Success Response
     */
    public function successResponse($data = null, string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Error Response
     */
    public function errorResponse(string $message = null, int $code, $errors = null): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors, // مخصص لتفاصيل الـ Validation مثلاً
        ], $code);
    }

    /**
     * Pagination Response
     */
    public function paginateResponse($paginatedData, string $message = null): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $paginatedData->items(),
            'pagination' => [
                'total'        => $paginatedData->total(),
                'count'        => $paginatedData->count(),
                'per_page'     => $paginatedData->perPage(),
                'current_page' => $paginatedData->currentPage(),
                'total_pages'  => $paginatedData->lastPage(),
            ],
        ], 200);
    }
}
