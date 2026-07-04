<?php

namespace App\Http\Requests\Admin\Settings;

use App\Models\Setting;
use App\Services\Admin\AdminSettingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadDiscoveryMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => 'required|string|in:discovery_media,about_media,hero_image',
            'action' => 'required|in:replace,append',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:3072',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('key') !== 'discovery_media' || $this->input('action') !== 'append') {
                return;
            }

            $currentImages = Setting::get('discovery_media', []);
            $currentCount = is_array($currentImages) ? count($currentImages) : 0;
            $incomingCount = count($this->file('images', []));

            if ($currentCount + $incomingCount > AdminSettingService::DISCOVERY_MEDIA_MAX) {
                $remaining = max(0, AdminSettingService::DISCOVERY_MEDIA_MAX - $currentCount);

                $validator->errors()->add(
                    'images',
                    "Discovery gallery cannot exceed ".AdminSettingService::DISCOVERY_MEDIA_MAX." images. You can upload up to {$remaining} more image(s)."
                );
            }
        });
    }
}
