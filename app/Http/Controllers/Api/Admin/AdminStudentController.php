<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminStudentRequest;
use App\Http\Resources\Admin\AdminStudentResource;
use App\Models\Student;
use App\Services\Admin\AdminStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Admin: Students
 */
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
            'search' => $request->query('search'),
            'status' => $request->query('status'),      // like: active
            'gender' => $request->query('gender'), // like: 1 or 0
            'group_id' => $request->query('group_id'), // like: 1 or 0
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

    /**
     * Update the status of the student.
     */
    public function updateStatus(Request $request, Student $student): JsonResponse
    {
        $request->validate(['status' => 'required|in:active,inactive']);

        // بنبعت الحالة مباشرة للسيرفس
        $updatedStudent = $this->studentService->updateStatus($student, $request->status);

        return $this->successResponse(
            new AdminStudentResource($updatedStudent),
            'Student status updated successfully'
        );
    }

    /**
     * Toggle the verification status of the student.
     */
    public function toggleVerify(Student $student): JsonResponse
    {
        $this->studentService->toggleVerify($student);

        // الأفضل نرجع الـ Student كامل بعد التحديث عشان الـ Frontend يحس بالتغيير
        return $this->successResponse(
            new AdminStudentResource($student->load('user')),
            'User verification toggled successfully'
        );
    }

    /**
     * Update the course group of the student.
     */
    public function updateCourseGroup(Request $request, Student $student, $courseId)
    {
        $request->validate([
            'group_id' => 'required|exists:learning_groups,id'
        ]);

        $success = $this->studentService->updateCourseGroup($student, (int)$courseId, $request->group_id);

        if ($success) {
            // Reload the student and group data from the database immediately
            $student->refresh();
            return $this->successResponse(
                null,
                'Course group updated successfully'
            );
        } else {
            return $this->errorResponse(
                'Cannot update group for a completed course or record not found',
                422
            );
        }
    }

    /**
     * Update the course status of the student.
     */
    public function updateCourseStatus(Request $request, Student $student, $courseId)
    {
        // Check that the sent value is logical (true or false)
        $request->validate([
            'is_completed' => 'required|boolean'
        ]);

        $success = $this->studentService->updateCourseStatus($student, (int)$courseId, $request->is_completed);

        if ($success) {
            // Reload the data immediately so the Resource is updated completely
            $student->refresh();

            return $this->successResponse(
                null,
                'Course status updated successfully'
            );
        }

        return $this->errorResponse(
            'Failed to update course status or record not found',
            400
        );
    }
}
