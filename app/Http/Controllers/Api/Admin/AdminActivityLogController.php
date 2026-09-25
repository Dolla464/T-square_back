<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\VerifyActivityLogPasswordRequest;
use App\Http\Resources\Admin\AdminActivityLogResource;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @tags Admin: Activity Logs
 */
class AdminActivityLogController extends Controller
{
    public function __construct(
        private AdminActivityLogService $activityLogService,
    ) {}

    public function verifyPassword(VerifyActivityLogPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The provided password is incorrect.')],
            ]);
        }

        $tokenData = $this->activityLogService->issueVerificationToken($user->id);

        return $this->successResponse($tokenData, 'Activity log access granted.');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->filled('search') ? $request->query('search') : null,
            'role' => $request->query('role'),
            'method' => $request->query('method'),
            'user_id' => $request->query('user_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $logs = $this->activityLogService->index(
            (int) $request->query('per_page', 15),
            $filters,
        );

        return $this->paginateResponse(
            $logs->through(fn ($log) => new AdminActivityLogResource($log)),
            'Activity logs retrieved successfully.',
        );
    }
}
