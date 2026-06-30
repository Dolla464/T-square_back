<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Public
 */
class SettingController extends Controller
{
    use ApiResponseTrait;

    // function calling site images for hero & about & discover sections
    public function getSettingByKey($key)
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $this->errorResponse('Setting not found', 404);
        }

        $processedValue = $setting->value;

        // معالجة القيمة بناءً على النوع
        switch ($setting->type) {
            case 'image':
                $processedValue = asset('site-media/'.$setting->value);
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

    // function to toggle the maintenance mode
    public function toggleMaintenance(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'status' => 'required|boolean'
        ]);

        // Update the value in the database (1 or 0)
        DB::table('settings')
            ->where('key', 'maintenance_mode')
            ->update([
                'value' => $request->status ? '1' : '0',
                'updated_at' => now()
            ]);

        return $this->successResponse([ 
            'message' => $request->status ? 'Maintenance mode enabled successfully' : 'Maintenance mode disabled and the website is now working'
        ], 'Maintenance mode toggled successfully');
    }
}
