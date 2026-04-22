<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ContactUsRequest;
use App\Services\User\ContactUsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Throwable;

class ContactUsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ContactUsService $contactUsService
    ) {}

    public function store(ContactUsRequest $request): JsonResponse
    {
        try {
            $contact = $this->contactUsService->store($request->validated());

            return $this->successResponse(
                data: $contact,
                message: 'Your message has been received. We will get back to you soon.'
            );
        } catch (Throwable $e) {
            return $this->errorResponse(
                message: 'Failed to submit your message. Please try again.',
                errors: app()->isLocal() ? $e->getMessage() : null,
                code: 500
            );
        }
    }
}
