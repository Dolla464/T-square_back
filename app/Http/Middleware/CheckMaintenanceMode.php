<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Get the maintenance mode setting from the settings table
        $maintenanceSetting = DB::table('settings')->where('key', 'maintenance_mode')->first();

        $isMaintenanceOn = $maintenanceSetting && $maintenanceSetting->value == '1';

        // 2. If the maintenance mode is enabled (ON)
        if ($isMaintenanceOn) {

            // Exclude certain paths from the closure to prevent the loop (admin dashboard, login page, and the maintenance page itself)
            if ($request->is('maintenance') || $request->is('admin/*') || $request->is('login') || $request->is('api/admin/*')) {
                return $next($request);
            }

            // Exclude the admin user. Token-based API requests have no web session,
            // so we must resolve the user through the sanctum guard first (then fall
            // back to the default web guard) before checking the admin role.
            $user = $request->user('sanctum') ?? $request->user();
            if ($user && $user->hasRole('admin')) {
                return $next($request);
            }

            // 3. Distinguish between API requests and regular Web requests
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The platform is in maintenance mode currently to update and improve services, we will be back soon.'
                ], 503); // 503 is the global code for maintenance status (Service Unavailable)
            }

            // If a regular user (student or visitor) on the Web, redirect them immediately to the maintenance page
            return redirect()->route('maintenance.page');
        }

        return $next($request);
    }
}