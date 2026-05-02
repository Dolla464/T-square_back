<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Certificate\CertificateResource;
use App\Models\Enrollment;
use App\Services\User\CertificateService;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $service)
    {
        $this->certificateService = $service;
    }

    // بيانات للفرونت عشان لو عايز البيانات قبل التحميل
    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['student', 'course']);
        return new CertificateResource($enrollment);
    }

    /**
     * تحميل الشهادة
     */
    public function download(Enrollment $enrollment)
    {
        $enrollment->load(['student', 'course']);

        if (!$enrollment->is_completed) {
            return response()->json([
                'status' => 'error',
                'message' => 'عذراً، الشهادة غير متاحة حالياً.'
            ], 403);
        }

        // ملاحظة: هنا بنرجع الـ Service علي طول لأنها بترجع Response من نوع Stream/File
        return $this->certificateService->generateLiveCertificate($enrollment);
    }
}
