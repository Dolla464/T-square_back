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

            // أولاً: الاستثناءات المباشرة (الرووتس المسموحة للكل وقت الصيانة)
            if (
                $request->is('maintenance') ||
                $request->is('admin/*') ||
                $request->is('login') ||
                $request->is('api/admin/*') ||
                $request->is('api/settings/maintenance_mode') ||
                $request->is('api/login') ||
                $request->is('api/register')
            ) {
                return $next($request);
            }

            // ── التعديل المنقذ هنا ──
            // نجبر لارافيل تشيك على التوكن من خلال الـ sanctum guard يدوياً
            if ($request->bearerToken()) {
                $user = auth('sanctum')->user(); // قراءة المستخدم من التوكن المبعوث في الـ Header

                // لو التوكن سليم والمستخدم أدمن، عَدّيه يفتح أي API هو عايزه!
                if ($user && $user->hasRole('admin')) {
                    return $next($request);
                }
            }

            // لو مفيش توكن، أو التوكن مش بتاع أدمن (طالب أو زائر عادي)
            // 3. التمييز بين طلبات الـ API والـ Web العادية
            if ($request->is('api/*') || $request->wantsJson()) {
                abort(503, 'The platform is in maintenance mode currently.');
            }

            return redirect()->route('maintenance.page');
        }

        return $next($request);
    }
}
