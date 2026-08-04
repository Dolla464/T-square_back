<?php

namespace App\Http\Middleware;

use App\Models\AttendanceDevice;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateAttendanceDevice
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->input('device_id');

        if (! is_string($deviceId) || $deviceId === '') {
            return $this->structuredErrorResponse(
                error: 'Device identification required.',
                code: 'FORBIDDEN',
                httpCode: 403,
            );
        }

        $device = AttendanceDevice::query()
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return $this->structuredErrorResponse(
                error: 'Unknown or inactive attendance device.',
                code: 'FORBIDDEN',
                httpCode: 403,
            );
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set('attendance_device', $device);

        return $next($request);
    }
}
