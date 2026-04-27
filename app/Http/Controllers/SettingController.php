<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponseTrait;

    // function calling site images for hero & about & discover sections
    public function getSettingByKey($key)
{
    $setting = Setting::where('key', $key)->first();

    if (!$setting) {
        return $this->errorResponse('Setting not found', 404);
    }

    $processedValue = $setting->value;

    // معالجة القيمة بناءً على النوع
    switch ($setting->type) {
        case 'image':
            $processedValue = asset('site-media/' . $setting->value);
            break;

        case 'json':
            // تحويل النص المحفوظ كـ JSON إلى مصفوفة PHP أو كائن
            $processedValue = json_decode($setting->value, true);
            break;

        case 'boolean':
            $processedValue = filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            break;
            
        // في حالة 'string' ستبقى القيمة كما هي
    }

    return $this->successResponse(
        ['key' => $key, 'value' => $processedValue],
        'Data fetched successfully'
    );
}
}
