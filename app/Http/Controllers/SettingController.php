<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicSettingResource;
use App\Models\Setting;
use App\Support\PublicSettingAllowlist;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Public
 */
class SettingController extends Controller
{
    use ApiResponseTrait;

    public function getSettingByKey($key)
    {
        if (! PublicSettingAllowlist::isAllowed($key)) {
            return $this->structuredErrorResponse(
                error: 'Resource not found',
                code: 'NOT_FOUND',
                httpCode: 404,
            );
        }

        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $this->structuredErrorResponse(
                error: 'Resource not found',
                code: 'NOT_FOUND',
                httpCode: 404,
            );
        }

        return $this->successResponse(
            (new PublicSettingResource($setting))->resolve(),
            'Data fetched successfully'
        );
    }

    public function getMaintenanceStatus()
    {
        return $this->successResponse(
            ['value' => (bool) Setting::get('maintenance_mode', false)],
            'Maintenance status fetched successfully'
        );
    }

    public function toggleMaintenance(Request $request)
    {
        $request->validate([
            'status' => 'required|boolean'
        ]);

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
