<?php

namespace App\Http\Requests\Api\Admin\Certificate;

use App\Enums\CertificateStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:learning_groups,id'],
            'status'   => ['sometimes', 'nullable', new Enum(CertificateStatus::class)],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
