<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Certificate\CertificateResource;
use App\Models\Enrollment;
use App\Services\User\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CertificateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $service)
    {
        $this->certificateService = $service;
    }

    /**
     * List all certificates for the authenticated student.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $student = $user->student;

        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student profile not found.',
            ], 404);
        }

        $enrollments = Enrollment::query()
            ->with(['student', 'course'])
            ->where('student_id', $student->id)
            ->where('is_completed', true)
            ->latest('completed_at')
            ->get();

        return CertificateResource::collection($enrollments);
    }

    public function show(Enrollment $enrollment)
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->loadMissing('student');

        if (! $user->student || $enrollment->student_id !== $user->student->id) {
            $payload = [
                'status' => 'error',
                'message' => 'Forbidden: This user is not the owner of this certificate.',
            ];
            if (config('app.debug')) {
                $payload['debug_info'] = [
                    'user_id' => $user->id,
                    'has_student_profile' => (bool) $user->student,
                ];
            }

            return response()->json($payload, 403);
        }
        $enrollment->load(['student', 'course']);

        if ($enrollment->is_completed) {
            $this->certificateService->issueCertificate($enrollment);
        }

        return new CertificateResource($enrollment);
    }

    /**
     * Stream the certificate file inline for in-browser (iframe) preview.
     */
    public function view(Enrollment $enrollment)
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $student = $user?->student;

        if (! $student || $enrollment->student_id !== $student->id) {
            $payload = [
                'status' => 'error',
                'message' => 'Sorry, you are not authorized to view this certificate.',
            ];
            if (config('app.debug')) {
                $payload['debug_info'] = [
                    'auth_student_id' => $student?->id,
                    'enrollment_student_id' => $enrollment->student_id,
                ];
            }

            return response()->json($payload, 403);
        }

        if (! $enrollment->is_completed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sorry, the certificate is not available because the course is not completed yet.',
            ], 403);
        }

        // Issue (or fetch) the certificate, then stream it inline for preview.
        $certificate = $this->certificateService->issueCertificate($enrollment);

        return $this->certificateService->viewFile($certificate);
    }

    /**
     * Download Certificate
     */
    public function download(Enrollment $enrollment)
    {
        Log::info('Certificate download route hit', [
            'enrollment_id' => $enrollment->id,
            'path' => request()->path(),
            'method' => request()->method(),
        ]);

        $user = auth('sanctum')->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $student = $user?->student;

        if (! $student || $enrollment->student_id !== $student->id) {
            $payload = [
                'status' => 'error',
                'message' => 'Sorry, you are not authorized to download this certificate.',
            ];
            if (config('app.debug')) {
                $payload['debug_info'] = [
                    'auth_student_id' => $student?->id,
                    'enrollment_student_id' => $enrollment->student_id,
                ];
            }

            return response()->json($payload, 403);
        }

        if (! $enrollment->is_completed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sorry, the certificate is not available because the course is not completed yet.',
            ], 403);
        }

        // Issue or fetch the certificate (force regeneration to pick up updated tags/instructor)
        $certificate = $this->certificateService->issueCertificate($enrollment, true);

        // Stream the file inline so the SPA can build the download blob itself
        // (matches the Admin download flow and bypasses download-manager hijacking).
        return $this->certificateService->downloadFile($certificate);
    }
}
