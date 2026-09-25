<?php

namespace App\Http\Middleware;

use App\Jobs\LogActivityJob;
use App\Services\ActivityLog\ActivityLogWriter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogActivityMiddleware
{
    /** @var list<string> */
    private const GUEST_AUTH_ROUTES = [
        'login',
        'register',
        'password.email',
        'password.store',
    ];

    public function __construct(
        private ActivityLogWriter $writer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        [$userId, $userName, $role] = $this->resolveActor($request);

        try {
            $payload = $this->writer->buildPayload(
                $request,
                $response->getStatusCode(),
                $userId,
                $userName,
                $role,
            );

            LogActivityJob::dispatch($payload);
        } catch (\Throwable $exception) {
            Log::warning('Activity log dispatch failed', [
                'message' => $exception->getMessage(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->isMethod('OPTIONS')) {
            return true;
        }

        if ($request->is('up')) {
            return true;
        }

        $path = $request->path();

        if ($path === 'api/notifications/unread-count') {
            return true;
        }

        if (str_starts_with($path, 'api/admin/activity-logs')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{0: ?int, 1: ?string, 2: string}
     */
    private function resolveActor(Request $request): array
    {
        if ($this->isGuestAuthRoute($request)) {
            return [null, null, 'guest'];
        }

        $user = $request->user();

        if (! $user) {
            return [null, null, 'guest'];
        }

        return [
            $user->id,
            $user->name,
            $user->getRoleNames()->first() ?? 'guest',
        ];
    }

    private function isGuestAuthRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return $routeName !== null && in_array($routeName, self::GUEST_AUTH_ROUTES, true);
    }
}
