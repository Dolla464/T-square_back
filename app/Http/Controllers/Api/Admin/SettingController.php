<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @tags Admin: Settings
 */
class SettingController extends Controller
{
    use ApiResponseTrait;

    /**
     * Whitelist of settings the admin dashboard is allowed to update,
     * together with the storage type and the logical group they belong to.
     */
    private const EDITABLE_SETTINGS = [
        'site_name'        => ['type' => 'string',  'group' => 'general'],
        'contact_email'    => ['type' => 'string',  'group' => 'general'],
        'whatsapp'         => ['type' => 'string',  'group' => 'social'],
        'facebook_url'     => ['type' => 'string',  'group' => 'social'],
        'maintenance_mode' => ['type' => 'boolean', 'group' => 'general'],
        'instagram_url'    => ['type' => 'string',  'group' => 'social'],
        'linkedin_url'     => ['type' => 'string',  'group' => 'social'],
        'hero_title_en'    => ['type' => 'string',  'group' => 'general'],
        'hero_title_ar'    => ['type' => 'string',  'group' => 'general'],
        'hero_title_highlight_en' => ['type' => 'string',  'group' => 'general'],
        'hero_title_highlight_ar' => ['type' => 'string',  'group' => 'general'],
        'hero_subtitle_en' => ['type' => 'string',  'group' => 'general'],
        'hero_subtitle_ar' => ['type' => 'string',  'group' => 'general'],
    ];

    /**
     * Update a single general setting by key.
     *
     * Drives both the "General Settings" form and the maintenance-mode toggle
     * from the admin dashboard. The frontend posts one { key, value } pair per
     * request, so each call is validated against the rules for that key.
     */
    public function update(Request $request): JsonResponse
    {
        $key = $request->input('key');

        // Normalize empty strings to null so optional fields (whatsapp, facebook_url)
        // can be cleared without tripping the "url" rule, while required fields
        // (site_name, contact_email) still correctly fail the "required" rule.
        if ($request->input('value') === '') {
            $request->merge(['value' => null]);
        }

        $validated = $request->validate([
            'key'   => ['required', 'string', Rule::in(array_keys(self::EDITABLE_SETTINGS))],
            'value' => $this->valueRulesFor($key),
        ]);

        $key   = $validated['key'];
        $value = $validated['value'];
        $meta  = self::EDITABLE_SETTINGS[$key];

        // Maintenance mode is stored as a normalized boolean string ("1" / "0").
        if ($key === 'maintenance_mode') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        Setting::set($key, $value, $meta['type'], $meta['group']);

        return $this->successResponse(
            ['key' => $key, 'value' => Setting::get($key)],
            'Setting updated successfully'
        );
    }

    /**
     * Get the current status of the maintenance mode so the dashboard toggle
     * can reflect the real state when the settings page is opened.
     */
    public function getMaintenanceStatus(): JsonResponse
    {
        return $this->successResponse(
            ['is_maintenance_on' => (bool) Setting::get('maintenance_mode', false)],
            'Current maintenance mode status.'
        );
    }

    /**
     * Resolve the validation rules for the "value" field based on the key.
     */
    private function valueRulesFor(?string $key): array
    {
        return match ($key) {
            'site_name'        => ['required', 'string', 'max:100'],
            'contact_email'    => ['required', 'email:rfc', 'max:255'],
            'whatsapp'         => ['nullable', 'string', 'max:30'],
            'facebook_url'     => ['nullable', 'url', 'max:255'],
            'instagram_url'    => ['nullable', 'url', 'max:255'],
            'linkedin_url'     => ['nullable', 'url', 'max:255'],
            'hero_title_en'    => ['nullable', 'string', 'max:255'],
            'hero_title_ar'    => ['nullable', 'string', 'max:255'],
            'hero_title_highlight_en' => ['nullable', 'string', 'max:255'],
            'hero_title_highlight_ar' => ['nullable', 'string', 'max:255'],
            'hero_subtitle_en' => ['nullable', 'string', 'max:255'],
            'hero_subtitle_ar' => ['nullable', 'string', 'max:255'],
            'maintenance_mode' => ['required'],
            default            => ['nullable'],
        };
    }
}
