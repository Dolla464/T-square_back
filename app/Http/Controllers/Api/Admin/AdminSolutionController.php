<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSolutionRequest;
use App\Http\Requests\Admin\UpdateSolutionRequest;
use App\Http\Resources\Admin\Solution\AdminSolutionResource;
use App\Models\Solution;
use App\Services\Admin\AdminSolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Admin: Solutions
 */
class AdminSolutionController extends Controller
{
    private AdminSolutionService $solutionService;

    public function __construct(AdminSolutionService $solutionService)
    {
        $this->solutionService = $solutionService;
    }

    /**
     * Display a listing of solutions
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $solutions = $this->solutionService->index(15, $search);

        return $this->paginateResponse($solutions->through(function ($solution) {
            return new AdminSolutionResource($solution);
        }), 'Solutions retrieved successfully');
    }

    /**
     * Store a newly created solution in database
     */
    public function store(StoreSolutionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $solution = $this->solutionService->store($data);

        return $this->successResponse(
            new AdminSolutionResource($solution),
            'Solution created successfully',
            201
        );
    }

    /**
     * Display the specified solution
     */
    public function show(Solution $solution): JsonResponse
    {
        $solution = $this->solutionService->show($solution);

        return $this->successResponse(
            new AdminSolutionResource($solution),
            'Solution retrieved successfully'
        );
    }

    /**
     * Update the specified solution in database
     */
    public function update(UpdateSolutionRequest $request, Solution $solution): JsonResponse
    {
        $data = $request->validated();
        $solution = $this->solutionService->update($solution, $data);

        return $this->successResponse(
            new AdminSolutionResource($solution),
            'Solution updated successfully'
        );
    }

    /**
     * Remove the specified solution from database
     */
    public function destroy(Solution $solution): JsonResponse
    {
        $this->solutionService->destroy($solution);

        return $this->successResponse(
            null,
            'Solution deleted successfully'
        );
    }
}
