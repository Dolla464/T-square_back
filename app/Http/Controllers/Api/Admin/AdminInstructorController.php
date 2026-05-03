<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminInstructorRequest;
use App\Http\Resources\Admin\AdminInstructorResource;
use App\Models\Instructor;
use App\Services\Admin\AdminInstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInstructorController extends Controller
{
    private AdminInstructorService $instructorService;

    public function __construct(AdminInstructorService $instructorService)
    {
        $this->instructorService = $instructorService;
    }

    /**
     * Display a listing of the instructors.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $instructors = $this->instructorService->index($perPage);

        $paginatedData = $instructors->through(function ($instructor) {
            return new AdminInstructorResource($instructor);
        });

        return $this->paginateResponse($paginatedData, 'Instructors retrieved successfully');
    }

    /**
     * Display the specified instructor.
     */
    public function show(Instructor $instructor): JsonResponse
    {
        $instructor = $this->instructorService->show($instructor);

        return $this->successResponse(
            new AdminInstructorResource($instructor),
            'Instructor retrieved successfully'
        );
    }

    /**
     * Update the specified instructor in storage.
     */
    public function update(UpdateAdminInstructorRequest $request, Instructor $instructor): JsonResponse
    {
        $updatedInstructor = $this->instructorService->update($instructor, $request->validated());

        return $this->successResponse(
            new AdminInstructorResource($updatedInstructor),
            'Instructor updated successfully'
        );
    }

    /**
     * Remove the specified instructor from storage.
     */
    public function destroy(Instructor $instructor): JsonResponse
    {
        $this->instructorService->destroy($instructor);

        return $this->successResponse(
            null,
            'Instructor deleted successfully'
        );
    }
}
