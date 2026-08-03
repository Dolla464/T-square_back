<?php

namespace App\Support;

final class PublicSettingAllowlist
{
    public const KEYS = [
        'site_name',
        'site_logo',
        'contact_email',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
        'whatsapp',
        'hero_title_en',
        'hero_title_ar',
        'hero_title_highlight_en',
        'hero_title_highlight_ar',
        'hero_subtitle_en',
        'hero_subtitle_ar',
        'discovery_media',
        'about_media',
        'hero_image',
    ];

    public static function isAllowed(string $key): bool
    {
        if (in_array($key, self::KEYS, true)) {
            return true;
        }

        if (self::containsSensitiveFragment($key)) {
            return false;
        }

        return false;
    }

    private static function containsSensitiveFragment(string $key): bool
    {
        $lower = strtolower($key);

        foreach (['api_key', 'secret', 'stripe', 'password', 'maintenance_mode'] as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
