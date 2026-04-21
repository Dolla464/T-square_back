<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\SolutionResource;
use App\Models\Solution;
use App\Services\User\SolutionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class SolutionsController extends Controller
{
    use ApiResponseTrait;

    protected $solutionService;

    public function __construct(SolutionService $solutionService)
    {
        $this->solutionService = $solutionService;
    }

    /**
     * Display a listing of all solutions with filters
     */
    public function index(Request $request)
    {
        $solutions = $this->solutionService->getAllSolutions($request->all());
        
        return response()->json([
            'status' => 'success',
            'message' => 'Solutions fetched successfully',
            'data' => SolutionResource::collection($solutions->items()),
            'pagination' => [
                'total' => $solutions->total(),
                'count' => $solutions->count(),
                'per_page' => $solutions->perPage(),
                'current_page' => $solutions->currentPage(),
                'total_pages' => $solutions->lastPage(),
            ],
        ], 200);
    }

    /**
     * Display the specified solution
     */
    public function show(Solution $solution)
    {
        $solution = $this->solutionService->getSolutionById($solution);
        return $this->successResponse(new SolutionResource($solution), 'Solution fetched successfully');
    }
}
