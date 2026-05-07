<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminStudentRequest;
use App\Http\Resources\Admin\AdminStudentResource;
use App\Models\Student;
use App\Services\Admin\AdminStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStudentController extends Controller
{
    private AdminStudentService $studentService;

    public function __construct(AdminStudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Display a listing of the students.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search'      => $request->query('search'),
            'status'      => $request->query('status'),      // like: active
            'is_verified' => $request->query('is_verified'), // like: 1 or 0
        ];
    
        $students = $this->studentService->index(
            $request->query('per_page', 10), 
            $filters
        );
    
        return $this->paginateResponse($students->through(function ($student) {
            return new AdminStudentResource($student);
        }), 'Students retrieved successfully');
    }

    /**
     * Display the specified student.
     */
    public function show(Student $student): JsonResponse
    {
        $student = $this->studentService->show($student);

        return $this->successResponse(
            new AdminStudentResource($student),
            'Student retrieved successfully'
        );
    }

    /**
     * Update the specified student in storage.
     */
    public function update(UpdateAdminStudentRequest $request, Student $student): JsonResponse
    {
        $updatedStudent = $this->studentService->update($student, $request->validated());

        return $this->successResponse(
            new AdminStudentResource($updatedStudent),
            'Student updated successfully'
        );
    }

    /**
     * Remove the specified student from storage.
     */
    public function destroy(Student $student): JsonResponse
    {
        $this->studentService->destroy($student);

        return $this->successResponse(
            null,
            'Student deleted successfully'
        );
    }
}
