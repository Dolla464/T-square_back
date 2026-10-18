<?php

namespace App\Http\Requests\Admin;

use App\Models\GroupAttendanceCardBulkOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchGroupAttendanceCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_operation_id' => ['required', 'uuid'],
            'mode' => [
                'required',
                'string',
                Rule::in([
                    GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
                    GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY,
                    GroupAttendanceCardBulkOperation::MODE_ISSUE_ALL,
                ]),
            ],
            'confirm_reissue' => ['sometimes', 'boolean'],
            'revocation_reason' => ['nullable', 'string', 'max:500'],
            'batch_cursor' => ['required', 'array'],
            'batch_cursor.from_sequence' => ['required', 'integer', 'min:0'],
            'batch_cursor.to_sequence' => ['required', 'integer', 'min:0'],
        ];
    }
}
