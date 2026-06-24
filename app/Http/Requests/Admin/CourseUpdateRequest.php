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

            // Tags
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],

            // Learnings (المصفوفة التي أضفناها مؤخراً)
            'learnings' => ['sometimes', 'nullable', 'array'],
            'learnings.*' => ['nullable', 'string', 'max:500'],

            // Previews
            'previews' => ['nullable', 'array'],
            'previews.*.id' => ['sometimes', 'nullable'], // تركناها مرنة للتعامل مع الـ IDs الجديدة أو القديمة
            'previews.*.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'previews.*.description' => ['nullable', 'string'],
            'previews.*.video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:512000'],
            'previews.*.video_url' => ['nullable', 'string'],
            'previews.*.video_provider' => ['nullable', 'string', 'in:youtube,vimeo,upload,external,html5'], // أضفنا html5 احتياطاً
            'previews.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'previews.*.duration_seconds' => ['nullable'], // مضافة لاستقبال المدة المحسوبة من الفرونت
        ];
    }

    /**
     * تجهيز البيانات قبل الـ Validation لضمان التعامل الصحيح مع الـ Booleans القادمة من FormData
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_featured' => filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN),
            'is_free'     => filter_var($this->is_free, FILTER_VALIDATE_BOOLEAN),
        ]);
        
        // تنظيف القيم التي قد تُرسل كنصوص "null" أو "undefined" من React
        if ($this->has('published_at') && ($this->published_at === 'null' || !$this->published_at)) {
            $this->merge(['published_at' => null]);
        }
    }
}