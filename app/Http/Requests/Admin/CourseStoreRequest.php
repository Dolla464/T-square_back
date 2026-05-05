<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CourseStoreRequest extends FormRequest
{
	public function authorize()
	{
		// assume admin middleware handles authorization
		return true;
	}

	public function rules()
	{
		return [
			'title' => ['required', 'string', 'max:255'],
			'short_description' => ['nullable', 'string', 'max:500'],
			'description' => ['nullable', 'string'],
			'thumbnail' => ['nullable', 'image', 'max:5120'], // up to 5MB
			'cover_image' => ['nullable', 'image', 'max:5120'],
			'preview_video' => ['nullable', 'string', 'max:191'],
			'google_drive_link' => ['nullable', 'url'],
			'attendance_type' => ['nullable', 'in:online,offline,hybrid'],
			'price_before' => ['nullable', 'numeric', 'min:0'],
			'discount_price' => ['nullable', 'numeric', 'min:0'],
			'level' => ['nullable', 'string', 'max:50'],
			'language' => ['nullable', 'string', 'max:50'],
			'duration_weeks' => ['nullable', 'integer', 'min:0'],
			'duration_hours' => ['nullable', 'numeric', 'min:0'],
			'status' => ['nullable', 'string', 'in:draft,published,archived'],
			'is_featured' => ['sometimes', 'boolean'],
			'is_free' => ['sometimes', 'boolean'],
			'category_id' => ['nullable', 'exists:categories,id'],
			'instructor_id' => ['nullable', 'exists:instructors,id'],
			'published_at' => ['nullable', 'date'],
		];
	}
}

