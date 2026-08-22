<?php

namespace App\Http\Requests\Admin;

use App\Models\Lesson;
use App\Services\Google\GoogleDriveUrlParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LessonStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
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
            $source = $this->input('video_source_type', Lesson::VIDEO_SOURCE_NONE);

            if ($source === Lesson::VIDEO_SOURCE_GOOGLE_DRIVE) {
                $url = $this->input('google_drive_url');

                if (! $url) {
                    $validator->errors()->add('google_drive_url', 'Google Drive URL is required when video source is Google Drive.');

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
