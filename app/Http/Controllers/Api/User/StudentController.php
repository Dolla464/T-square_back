<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Models\Student;
use App\Services\StudentService;
use App\Traits\ApiResponseTrait;

class StudentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private StudentService $studentService)
    {
    }

    /**
     * Display a listing of students.
     */
    public function index()
    {
        $students = $this->studentService->getPaginated();

        return $this->successResponse($students, 'تم جلب بيانات الطلاب بنجاح');
    }

    /**
     * Store a newly created student.
     */
    public function store(StoreStudentRequest $request)
    {
        try {
            $student = $this->studentService->create($request->validated());

            return $this->successResponse(
                $student,
                'تم إنشاء الطالب بنجاح',
                201
            );
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Display the specified student.
     */
    public function show(Student $student)
    {
        return $this->successResponse(
            $this->studentService->show($student),
            'تم جلب بيانات الطالب بنجاح'
        );
    }

    /**
     * Update the specified student.
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        try {
            $result = $this->studentService->update($student, $request->validated());

            if (!$result['updated']) {
                return $this->successResponse(
                    $result['student'],
                    'لا يوجد تغييرات للتحديث'
                );
            }

            return $this->successResponse(
                $result['student'],
                'تم تحديث بيانات الطالب بنجاح'
            );
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Remove the specified student.
     */
    public function destroy(Student $student)
    {
        try {
            $this->studentService->delete($student);

            return $this->successResponse(null, 'تم حذف الطالب بنجاح');
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }
}
