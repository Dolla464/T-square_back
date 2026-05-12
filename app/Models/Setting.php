<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    // يمسح الكاش اوتوماتيك اول ما اى قيمة تتعدل
    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('app_settings');
        });
    }

    protected $fillable = ['key', 'value', 'type', 'group_name'];

    /**
     * دالة مساعدة لجلب قيمة أي إعداد بسرعة
     * استخدامها: Setting::get('site_name')
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        // تحويل القيمة بناءً على النوع
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }
}
