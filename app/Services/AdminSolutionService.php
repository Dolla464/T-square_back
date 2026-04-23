<?php

namespace App\Services;

use App\Models\Solution;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class AdminSolutionService
{
    /**
     * Get all solutions with pagination
     */
    public function index(int $perPage = 15)
    {
        return Solution::paginate($perPage);
    }

    /**
     * Store a new solution
     */
    public function store(array $data): Solution
    {
        return DB::transaction(function () use ($data) {
            $solution = Solution::create([
                'title' => $data['title'],
                'description' => $data['description'],
            ]);

            // Attach tags if provided
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                $solution->tags()->attach($data['tag_ids']);
            }

            $solution->load('tags');

            return $solution;
        });
    }

    /**
     * Get a specific solution
     */
    public function show(Solution $solution): Solution
    {
        return $solution->load('tags');
    }

    /**
     * Update a solution
     */
    public function update(Solution $solution, array $data): Solution
    {
        return DB::transaction(function () use ($solution, $data) {
            $updateData = [];

            if (isset($data['title'])) {
                $updateData['title'] = $data['title'];
            }

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (!empty($updateData)) {
                $solution->update($updateData);
            }

            // Sync tags if provided
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                $solution->tags()->sync($data['tag_ids']);
            }

            $solution->load('tags');

            return $solution;
        });
    }

    /**
     * Delete a solution
     */
    public function destroy(Solution $solution): bool
    {
        return DB::transaction(function () use ($solution) {
            return $solution->delete();
        });
    }
}
