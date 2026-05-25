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
        // Check if the current request is "published" or "draft"
        $isPublished = $this->input('status') === 'published';

        return [
            'title'             => ['required', 'string', 'max:255'],
            'status'            => ['required', 'string', 'in:draft,published,archived'],
            'category_id'       => ['required', 'exists:categories,id'],
            'instructor_id'     => ['required', 'exists:instructors,id'],
            
            // Fields that become required only when published, and optional in draft
            'short_description' => [$isPublished ? 'required' : 'nullable', 'string', 'max:500'],
            'description'       => [$isPublished ? 'required' : 'nullable', 'string'],
            'cover_image'       => [$isPublished ? 'required' : 'nullable', 'image', 'max:5120'],
            'preview_video'     => ['nullable', 'string', 'max:191'],
            'google_drive_link' => [$isPublished ? 'required' : 'nullable', 'url'],
            'attendance_type'   => [$isPublished ? 'required' : 'nullable', 'in:online,offline,hybrid'],
            'price_before'      => [$isPublished ? 'required' : 'nullable', 'numeric', 'min:0'],
            'level'             => [$isPublished ? 'required' : 'nullable', 'string', 'max:50'],
            'language'          => [$isPublished ? 'required' : 'nullable', 'string', 'max:50'],
            'duration_weeks'    => [$isPublished ? 'required' : 'nullable', 'integer', 'min:0'],
            'duration_hours'    => [$isPublished ? 'required' : 'nullable', 'numeric', 'min:0'],

            // Optional fields always in both cases
            'thumbnail'         => ['nullable', 'image', 'max:5120'],
            'discount_price'    => ['nullable', 'numeric', 'min:0'],
            'is_featured'       => ['sometimes', 'boolean'],
            'is_free'           => ['sometimes', 'boolean'],
            'published_at'      => ['nullable', 'date'],

            // Tags: array of existing tag IDs
            'tags'              => ['sometimes', 'nullable', 'array'],
            'tags.*'            => ['integer', 'exists:tags,id'],

            // Learnings
            'learnings'         => ['sometimes', 'nullable', 'array'],
            'learnings.*'       => ['nullable', 'string', 'max:500'],

            // Previews
            'previews'                      => ['sometimes', 'nullable', 'array'],
            'previews.*.id'                 => ['sometimes', 'nullable', 'integer', 'exists:course_previews,id'],
            'previews.*.title'              => ['required_with:previews', 'nullable', 'string', 'max:255'],
            'previews.*.description'        => ['nullable', 'string'],
            'previews.*.video'              => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:512000'],
            'previews.*.video_url'          => ['nullable', 'string', 'max:500'],
            'previews.*.video_provider'     => ['nullable', 'string', 'in:youtube,vimeo,upload,external'],
            'previews.*.sort_order'         => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages()
    {
        return [
            'title.required'             => 'The title field is required.',
            'short_description.required' => 'The short description field is required.',
            'description.required'       => 'The description field is required.',
            'cover_image.required'       => 'The cover image field is required.',
            'preview_video.required'     => 'The preview video field is required.',
            'google_drive_link.required' => 'The Google Drive link field is required.',
            'attendance_type.required'   => 'The attendance type field is required.',
            'price_before.required'      => 'The price before field is required.',
            'level.required'             => 'The level field is required.',
            'language.required'          => 'The language field is required.',
            'duration_weeks.required'    => 'The duration weeks field is required.',
            'duration_hours.required'    => 'The duration hours field is required.',
            'status.required'            => 'The status field is required.',
            'category_id.required'       => 'The category id field is required.',
            'instructor_id.required'     => 'The instructor id field is required.',
            'previews.*.title.required'  => 'The previews title field is required.',
            'previews.*.description.required' => 'The previews description field is required.',
            'previews.*.video.required' => 'The previews video field is required.',
            'previews.*.video_url.required' => 'The previews video url field is required.',
            'previews.*.video_provider.required' => 'The previews video provider field is required.',
            'previews.*.sort_order.required' => 'The previews sort order field is required.',
        ];
    }
}