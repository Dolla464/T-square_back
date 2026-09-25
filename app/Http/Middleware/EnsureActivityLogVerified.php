<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivityLogVerified
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Activity-Log-Token');

        if (! is_string($token) || $token === '') {
            return $this->errorResponse('Activity log access requires password verification.', 403);
        }

        $userId = $request->user()?->id;

        if (! $userId) {
            return $this->errorResponse('Unauthorized access', 401);
        }

        $cacheKey = $this->cacheKey($userId, $token);

        if (! Cache::get($cacheKey)) {
            return $this->errorResponse('Activity log access token is invalid or expired.', 403);
        }

        return $next($request);
    }

    public static function cacheKey(int $userId, string $token): string
    {
        return 'activity_log_verified:'.$userId.':'.hash('sha256', $token);
    }
}
