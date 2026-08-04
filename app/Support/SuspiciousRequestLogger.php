<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuspiciousRequestLogger
{
    /**
     * @param  array<int, string>  $forbiddenFields
     */
    public static function log(Request $request, string $event, array $forbiddenFields): void
    {
        if ($forbiddenFields === []) {
            return;
        }

        Log::warning('suspicious.mass_assignment', [
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->path(),
            'forbidden' => array_values($forbiddenFields),
            'user_id' => $request->user()?->id,
        ]);
    }

    /**
     * @param  array<int, string>  $allowedKeys
     * @return array<int, string>
     */
    public static function detectExtraFields(Request $request, array $allowedKeys): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'current_password', 'token'];

        return collect(array_keys($request->all()))
            ->diff($allowedKeys)
            ->reject(fn (string $key) => in_array($key, $sensitiveKeys, true))
            ->values()
            ->all();
    }
}
