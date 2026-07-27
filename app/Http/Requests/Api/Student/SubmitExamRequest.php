<?php

namespace App\Http\Requests\Api\Student;

use App\DTO\AuthorizationResult;
use App\Services\Exam\ExamAttemptAuthorizationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubmitExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->user()->student;

        if (! $student) {
            $this->attributes->set('auth_result', AuthorizationResult::forbidden('Student profile not found.'));

            return false;
        }

        $attemptId = (int) $this->route('id');
        $result = app(ExamAttemptAuthorizationService::class)->checkSubmittable($attemptId, $student->id);
        $this->attributes->set('auth_result', $result);

        return $result->isAllowed();
    }

    protected function failedAuthorization(): void
    {
        /** @var AuthorizationResult|null $result */
        $result = $this->attributes->get('auth_result')
            ?? AuthorizationResult::forbidden('You are not allowed to submit this attempt.');

        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'error' => $result->getMessage() ?? 'Forbidden',
                'message' => $result->getMessage(),
                'code' => $result->getCode(),
            ], $result->getStatusCode())
        );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
