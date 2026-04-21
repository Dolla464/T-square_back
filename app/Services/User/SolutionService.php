<?php

namespace App\Services\User;

use App\Models\Solution;
use Illuminate\Pagination\LengthAwarePaginator;

class SolutionService
{
    /**
     * Get all solutions with pagination and filters
     */
    public function getAllSolutions(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        $query = Solution::with('tags');

        // Filter by search if provided
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        // Filter by tag if provided
        if (isset($filters['tag_id'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('tags.id', $filters['tag_id']);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a specific solution by ID with tags
     */
    public function getSolutionById(Solution $solution): Solution
    {
        return $solution->load('tags');
    }
}
