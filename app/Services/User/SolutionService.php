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
        return Solution::with('tags') 
        ->latest()       
        ->paginate($perPage);

    }

    /**
     * Get a specific solution by ID with tags
     */
    public function getSolutionById(Solution $solution): Solution
    {
        return $solution->load('tags');
    }
}
