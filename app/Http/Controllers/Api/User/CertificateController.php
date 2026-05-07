<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Certificate\CertificateResource;
use App\Models\Enrollment;
use App\Services\User\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->loadMissing('student');

        if (!$user->student || $enrollment->student_id !== $user->student->id) {
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
     * تحميل الشهادة
     */
    public function download(Enrollment $enrollment)
    {
        Log::info('Certificate download route hit', [
            'enrollment_id' => $enrollment->id,
            'path' => request()->path(),
            'method' => request()->method(),
        ]);

        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $student = $user?->student;

        if (!$student || $enrollment->student_id !== $student->id) {
            $payload = [
                'status' => 'error',
                'message' => 'عذراً، لا تملك صلاحية تحميل هذه الشهادة.',
            ];
            if (config('app.debug')) {
                $payload['debug_info'] = [
                    'auth_student_id' => $student?->id,
                    'enrollment_student_id' => $enrollment->student_id,
                ];
            }

            return response()->json($payload, 403);
        }

        if (!$enrollment->is_completed) {
            return response()->json([
                'status' => 'error',
                'message' => 'عذراً، الشهادة غير متاحة لأن الكورس لم يكتمل بعد.',
            ], 403);
        }

        // Issue or fetch the certificate
        $certificate = $this->certificateService->issueCertificate($enrollment);

        // Download the file from storage
        if (!Storage::disk('public')->exists($certificate->certificate_url)) {
            return response()->json([
                'status' => 'error',
                'message' => 'ملف الشهادة غير موجود.',
            ], 404);
        }

        return Storage::disk('public')->download($certificate->certificate_url);
    }
}
