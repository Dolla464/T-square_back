<?php

namespace App\Http\Requests\Admin;

use App\Models\Lesson;
use App\Services\Google\GoogleDriveUrlParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LessonUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'video_source_type' => ['nullable', 'string', 'in:none,google_drive'],
            'google_drive_url' => ['nullable', 'string', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('video_source_type') && ! $this->has('google_drive_url')) {
                return;
            }

            $source = $this->input('video_source_type', Lesson::VIDEO_SOURCE_NONE);

            if ($source === Lesson::VIDEO_SOURCE_GOOGLE_DRIVE) {
                $url = $this->input('google_drive_url');
                $lesson = $this->route('lesson');

                if (! $url && ! $lesson?->google_drive_file_id) {
                    $validator->errors()->add('google_drive_url', 'Google Drive URL is required when video source is Google Drive.');

                    return;
                }

                if (! $url) {
                    return;
                }

                try {
                    app(GoogleDriveUrlParser::class)->parse($url);
                } catch (\InvalidArgumentException $e) {
                    $validator->errors()->add('google_drive_url', $e->getMessage());
                }
            }
        });
    }
}
