<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Certificate\IndexCertificateRequest;
use App\Http\Requests\Api\Admin\Certificate\UpdateCertificateRequest;
use App\Http\Resources\Admin\Certificate\AdminCertificateResource;
use App\Models\Certificate;
use App\Services\Admin\AdminCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCertificateController extends Controller
{
    public function __construct(
        private readonly AdminCertificateService $certificates,
    ) {}

    /**
     * Display a paginated listing of certificates along with status statistics.
     *
     * Query parameters (all optional):
     * search   (string)  – matches student name or course title
     * group_id (int)     – exact LearningGroup ID (must exist in learning_groups)
     * status   (string)  – issued | pending | revoked
     * per_page (int)     – default 10, max 100
     */
    public function index(IndexCertificateRequest $request): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        // 1. Get the data and statistics from the service
        $result = $this->certificates->index(
            $request->only(['search', 'group_id', 'status']),
            $perPage,
        );

        // 2. Convert the actual Paginator using the Resource you wrote
        $collection = AdminCertificateResource::collection($result['paginator'])->resource;

        // 3. Generate the default Response for the project (which reads the items() successfully)
        $response = $this->paginateResponse(
            $collection,
            'Certificates retrieved successfully',
        );

        // 4. Get the array of data that was automatically generated
        $responseData = $response->getData(true);

        // 5. Rebuild the data field to contain the certificates and statistics together
        $originalData = $responseData['data'] ?? [];

        $responseData['data'] = [
            'statistics'   => $result['stats'],
            'certificates' => $originalData
        ];

        // 6. Reset the new data for the Response and return it
        return $response->setData($responseData);
    }

    /**
     * Display the specified certificate.
     * Laravel resolves {certificate} automatically via Route Model Binding.
     */
    public function show(Certificate $certificate): JsonResponse
    {
        $certificate = $this->certificates->show($certificate);

        return $this->successResponse(
            new AdminCertificateResource($certificate),
            'Certificate retrieved successfully',
        );
    }

    /**
     * View the certificate file inline.
     */
    public function viewFile(Certificate $certificate): StreamedResponse
    {
        return $this->certificates->viewFile($certificate);
    }

    /**
     * Download the certificate file to the user's device.
     */
    public function downloadFile(Certificate $certificate): StreamedResponse
    {
        return $this->certificates->downloadFile($certificate);
    }

    /**
     * Update completion status or re-issue the certificate.
     */
    public function update(UpdateCertificateRequest $request, Certificate $certificate): JsonResponse
    {
        $updated = $this->certificates->update($certificate->id, $request->validated());

        return $this->successResponse(
            new AdminCertificateResource($updated),
            'Certificate updated successfully',
        );
    }

    /**
     * Remove the specified certificate from storage.
     */
    public function destroy(Certificate $certificate): Response
    {
        $this->certificates->destroy($certificate->id);

        return response()->noContent();
    }
}
