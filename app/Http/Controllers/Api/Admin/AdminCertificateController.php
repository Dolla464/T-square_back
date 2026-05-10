<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Certificate\UpdateCertificateRequest;
use App\Http\Resources\Admin\Certificate\AdminCertificateResource;
use App\Services\Admin\AdminCertificateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminCertificateController extends Controller
{
    public function __construct(private readonly AdminCertificateService $certificates) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        $paginator = $this->certificates->index([
            'search' => $request->string('search')->toString(),
        ], $perPage);

        return AdminCertificateResource::collection($paginator);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $certificate = $this->certificates->show((int) $id);

        return new AdminCertificateResource($certificate);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCertificateRequest $request, string $id)
    {
        $certificate = $this->certificates->update((int) $id, $request->validated());

        return new AdminCertificateResource($certificate);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): Response
    {
        $this->certificates->destroy((int) $id);

        return response()->noContent();
    }
}
