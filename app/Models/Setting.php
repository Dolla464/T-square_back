<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Support\HomePageCache;

class Setting extends Model
{
    // Clear the cache automatically when any value is changed
    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('app_settings');
            HomePageCache::forget();
        });
    }

    protected $fillable = ['key', 'value', 'type', 'group_name'];

    /**
     * Helper function to get the value of any setting quickly
     * Usage: Setting::get('site_name')
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        // Convert the value based on the type
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Helper function to save or update any setting quickly
     */
    public static function set($key, $value, $type = 'string', $group = 'general')
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $type === 'json' ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
                'type' => $type,
                'group_name' => $group
            ]
        );
    }
}
