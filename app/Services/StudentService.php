<?php

namespace App\Services;

use App\Models\Student;

class StudentService
{
    public function getPaginated(int $perPage = 15)
    {
        return Student::with(['user', 'learningGroup'])->paginate($perPage);
    }

    public function create(array $data): Student
    {
        $student = Student::create($data);

        return $student->load(['user', 'learningGroup']);
    }

    public function show(Student $student): Student
    {
        return $student->load(['user', 'learningGroup']);
    }

    public function update(Student $student, array $data): array
    {
        $filteredData = array_filter($data, function ($value, $key) use ($student) {
            if ($value === null || $value === '') {
                return false;
            }

            return $student->{$key} !== $value;
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($filteredData)) {
            return [
                'updated' => false,
                'student' => $student->load(['user', 'learningGroup']),
            ];
        }

        $student->update($filteredData);

        return [
            'updated' => true,
            'student' => $student->fresh()->load(['user', 'learningGroup']),
        ];
    }

    public function delete(Student $student): void
    {
        $student->delete();
    }
}
