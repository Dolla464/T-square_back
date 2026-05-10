<?php

namespace App\Http\Requests\Api\Admin\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Admin intent: control course completion and optionally re-issue the certificate.
            'is_completed' => ['sometimes', 'boolean'],
            'reissue' => ['sometimes', 'boolean'],
        ];
    }
}

