<?php

namespace App\Services\Admin;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminExamService
{
    /**
     * Filter and get the exams for the admin dashboard
     */
    public function getFilteredExamsForAdmin(array $filters): LengthAwarePaginator
    {
        // Load the course and questions automatically with the least number of queries
        $query = Exam::with(['course', 'updatedBy.admin', 'updatedBy.instructor'])
            ->withCount('questions');

        // 1. Filter the search (exam name, description, course name)
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

        // 2. Filter the status (Active / Inactive)
        if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('is_active', $filters['status']);
        }

        // 3. Filter the time (last week, month, year)
        if (!empty($filters['date_range'])) {
            $now = Carbon::now();
            $query->where('created_at', '>=', match ($filters['date_range']) {
                'last_week'  => $now->subWeek(),
                'last_month' => $now->subMonth(),
                'last_year'  => $now->subYear(),
            });
        }

        // Determine the number of items per page (default 10)
        $perPage = $filters['per_page'] ?? 10;

        // Execute the Pagination with the filters in the links
        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Create a new exam
     */
    public function createExam(array $data, ?int $updatedByUserId = null): Exam
    {
        if ($updatedByUserId !== null) {
            $data['updated_by'] = $updatedByUserId;
        }

        return Exam::create($data);
    }

    /**
     * Update the exam data
     */
    public function updateExam(Exam $exam, array $data, ?int $updatedByUserId = null): Exam
    {
        if ($updatedByUserId !== null) {
            $data['updated_by'] = $updatedByUserId;
        }

        $exam->update($data);

        return $exam;
    }

    /**
     * Delete the exam (Soft Delete)
     */
    public function deleteExam(Exam $exam): bool
    {
        return $exam->delete();
    }

    /**
     * Get the list of deleted exams (trash) with pagination
     */
    public function getTrashedExams(int $perPage = 10): LengthAwarePaginator
    {
        // onlyTrashed method returns only the records that have a deleted_at value
        return Exam::onlyTrashed()
            ->with(['course', 'updatedBy.admin', 'updatedBy.instructor'])
            ->withCount('questions')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Restore a deleted exam
     */
    public function restoreExam(int $id): ?Exam
    {
        // Use withTrashed to find the exam because it is hidden from the regular find method
        $exam = Exam::withTrashed()->findOrFail($id);
        $exam->restore();

        return $exam;
    }

    /**
     * Force delete the exam from the database
     */
    public function forceDeleteExam(int $id): bool
    {
        $exam = Exam::withTrashed()->findOrFail($id);
        return $exam->forceDelete(); // Real final deletion
    }

    /**
     * Change the active status of the exam (Enable / Disable)
     */
    public function toggleExamStatus(int $id, int $isActive, ?int $updatedByUserId = null): Exam
    {
        $exam = Exam::findOrFail($id);

        $payload = ['is_active' => $isActive];

        if ($updatedByUserId !== null) {
            $payload['updated_by'] = $updatedByUserId;
        }

        $exam->update($payload);

        return $exam;
    }

    public function recordExamUpdatedBy(int $examId, int $userId): void
    {
        Exam::whereKey($examId)->update([
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);
    }
}
