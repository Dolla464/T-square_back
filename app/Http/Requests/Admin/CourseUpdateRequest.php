<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CourseUpdateRequest extends FormRequest
{
	public function authorize()
	{
		return true;
	}

	public function rules()
	{
		return [
			'title' => ['sometimes', 'string', 'max:255'],
			'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
			'description' => ['sometimes', 'nullable', 'string'],
			'thumbnail' => ['sometimes', 'nullable', 'image', 'max:5120'],
			'cover_image' => ['sometimes', 'nullable', 'image', 'max:5120'],
			'preview_video' => ['sometimes', 'nullable', 'string', 'max:191'],
			'google_drive_link' => ['sometimes', 'nullable', 'url'],
			'attendance_type' => ['sometimes', 'nullable', 'in:online,offline,hybrid'],
			'price_before' => ['sometimes', 'nullable', 'numeric', 'min:0'],
			'discount_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
			'level' => ['sometimes', 'nullable', 'string', 'max:50'],
			'language' => ['sometimes', 'nullable', 'string', 'max:50'],
			'duration_weeks' => ['sometimes', 'nullable', 'integer', 'min:0'],
			'duration_hours' => ['sometimes', 'nullable', 'numeric', 'min:0'],
			'status' => ['sometimes', 'nullable', 'in:draft,published,archived'],
			'is_featured' => ['sometimes', 'boolean'],
			'is_free' => ['sometimes', 'boolean'],
			'category_id' => ['sometimes', 'nullable', 'exists:categories,id'],
			'instructor_id' => ['sometimes', 'nullable', 'exists:instructors,id'],
			'published_at' => ['sometimes', 'nullable', 'date'],
		];
	}
}

