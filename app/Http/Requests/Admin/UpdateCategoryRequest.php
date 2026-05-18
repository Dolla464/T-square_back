<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
        // Route model binding exposes the resolved Category as 'category'.
        $categoryId = $this->route('category')?->id;

        return [
            // 'unique' is intentionally omitted from name — slug uniqueness is
            // handled by the model's updating hook, which ignores the current record.
            'name'        => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:255',
            'image'       => 'nullable|string|max:255',
            // Prevent a category from becoming its own parent.
            'parent_id'   => [
                'nullable',
                'integer',
                'exists:categories,id',
                "not_in:{$categoryId}",
            ],
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
            'parent_id.not_in'   => 'A category cannot be its own parent.',
            'status.in'          => 'Status must be either "active" or "hidden".',
            'sort_order.integer' => 'Sort order must be a whole number.',
            'sort_order.min'     => 'Sort order must be zero or greater.',
        ];
    }
}
