<?php

namespace App\Services\Instructor;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class InstructorExamService
{
    /**
     * Filter and get exams scoped to the instructor's courses.
     */
    public function getFilteredExamsForInstructor(int $instructorId, array $filters): LengthAwarePaginator
    {
        $query = Exam::with('course')
            ->withCount('questions')
            ->whereHas('course', fn ($q) => $q->where('instructor_id', $instructorId));

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('course', function ($courseQuery) use ($search) {
                        $courseQuery->where('title', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('is_active', $filters['status']);
        }

        if (!empty($filters['date_range'])) {
            $now = Carbon::now();
            $query->where('created_at', '>=', match ($filters['date_range']) {
                'last_week'  => $now->subWeek(),
                'last_month' => $now->subMonth(),
                'last_year'  => $now->subYear(),
            });
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function createExam(array $data): Exam
    {
        return Exam::create($data);
    }

    public function updateExam(Exam $exam, array $data): Exam
    {
        $exam->update($data);

        return $exam;
    }

    public function deleteExam(Exam $exam): bool
    {
        return $exam->delete();
    }

    public function getTrashedExams(int $instructorId, int $perPage = 10): LengthAwarePaginator
    {
        return Exam::onlyTrashed()
            ->with('course')
            ->withCount('questions')
            ->whereHas('course', fn ($q) => $q->where('instructor_id', $instructorId))
            ->latest()
            ->paginate($perPage);
    }

    public function restoreExam(int $id, int $instructorId): ?Exam
    {
        $exam = Exam::onlyTrashed()
            ->whereHas('course', fn ($q) => $q->where('instructor_id', $instructorId))
            ->findOrFail($id);

        $exam->restore();

        return $exam;
    }

    public function forceDeleteExam(int $id, int $instructorId): bool
    {
        $exam = Exam::withTrashed()
            ->whereHas('course', fn ($q) => $q->where('instructor_id', $instructorId))
            ->findOrFail($id);

        return $exam->forceDelete();
    }

    public function toggleExamStatus(int $id, int $isActive, int $instructorId): Exam
    {
        $exam = Exam::whereHas('course', fn ($q) => $q->where('instructor_id', $instructorId))
            ->findOrFail($id);

        $exam->update(['is_active' => $isActive]);

        return $exam;
    }
}
