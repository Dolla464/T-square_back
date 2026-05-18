<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:255',
            'image'       => 'nullable|string|max:255',
            // parent_id is nullable — admin may create both top-level and child categories.
            // When provided it must resolve to an existing category.
            'parent_id'   => 'nullable|integer|exists:categories,id',
            'sort_order'  => 'nullable|integer|min:0',
            'status'      => 'nullable|in:active,hidden',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Category name is required.',
            'name.max'           => 'Category name must not exceed 100 characters.',
            'parent_id.exists'   => 'The selected parent category does not exist.',
            'status.in'          => 'Status must be either "active" or "hidden".',
            'sort_order.integer' => 'Sort order must be a whole number.',
            'sort_order.min'     => 'Sort order must be zero or greater.',
        ];
    }
}
