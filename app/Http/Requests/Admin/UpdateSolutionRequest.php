<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSolutionRequest extends FormRequest
{
    /**
     * Normalize incoming payload before validation.
     */
    protected function prepareForValidation(): void
    {
        $dataPayload = $this->input('data');
        $decodedData = [];

        if (is_string($dataPayload)) {
            $parsed = json_decode($dataPayload, true);
            if (is_array($parsed)) {
                $decodedData = $parsed;
            }
        } elseif (is_array($dataPayload)) {
            $decodedData = $dataPayload;
        }

        $title = $this->input('title')
            ?? $this->input('name')
            ?? $this->input('data.title')
            ?? $this->input('data.name')
            ?? ($decodedData['title'] ?? null)
            ?? ($decodedData['name'] ?? null);

        $description = $this->input('description')
            ?? $this->input('data.description')
            ?? ($decodedData['description'] ?? null);

        $tagIds = $this->input('tag_ids')
            ?? $this->input('data.tag_ids')
            ?? ($decodedData['tag_ids'] ?? null);

        $payload = [];

        if ($title !== null) {
            $payload['title'] = $title;
        }

        if ($description !== null) {
            $payload['description'] = $description;
        }

        if ($tagIds !== null) {
            $payload['tag_ids'] = $tagIds;
        }

        if (! empty($payload)) {
            $this->merge($payload);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Temporarily disabled for testing
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'The title must not exceed 255 characters.',
            'tag_ids.*.exists' => 'One or more selected tags do not exist.',
        ];
    }
}
