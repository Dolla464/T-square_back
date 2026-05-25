<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Payment\UpdatePaymentRequest;
use App\Http\Resources\Admin\Payment\AdminPaymentCollection;
use App\Http\Resources\Admin\Payment\AdminPaymentResource;
use App\Services\Admin\AdminPaymentAnalyticsService;
use App\Services\Admin\AdminPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly AdminPaymentService $payments,
        private readonly AdminPaymentAnalyticsService $analytics
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AdminPaymentCollection
    {
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        $paginator = $this->payments->index([
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
        ], $perPage);

        $stats = $this->analytics->getRecentStats();

        return new AdminPaymentCollection($paginator, $stats);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): AdminPaymentResource
    {
        $order = $this->payments->show($id);

        return new AdminPaymentResource($order);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentRequest $request, int $id): AdminPaymentResource
    {
        $order = $this->payments->update($id, $request->validated());

        return new AdminPaymentResource($order);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): Response
    {
        $this->payments->destroy($id);

        return response()->noContent();
    }
}
