<?php

namespace App\Services\ActivityLog;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogWriter
{
    public function __construct(
        private RequestDataSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function write(array $payload): ActivityLog
    {
        return ActivityLog::create($payload);
    }

    public function buildPayload(
        Request $request,
        int $responseStatus,
        ?int $userId,
        ?string $userName,
        string $role,
    ): array {
        $route = $request->route();
        $routeName = $route?->getName();
        $method = strtoupper($request->method());
        $path = '/'.$request->path();

        return [
            'user_id' => $userId,
            'user_name' => $userName,
            'role' => $role,
            'http_method' => $method,
            'route_name' => $routeName,
            'path' => $path,
            'description' => $this->buildDescription($method, $path, $routeName),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $this->sanitizer->sanitize($this->collectRequestData($request)),
            'response_status' => $responseStatus,
            'created_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectRequestData(Request $request): array
    {
        $query = $request->query();
        $body = $request->except(array_keys($query));

        if ($body === [] && $query === []) {
            return [];
        }

        if ($body === []) {
            return $query;
        }

        if ($query === []) {
            return $body;
        }

        return [
            'query' => $query,
            'body' => $body,
        ];
    }

    private function buildDescription(string $method, string $path, ?string $routeName): string
    {
        if ($routeName) {
            return str_replace('.', ' ', $routeName);
        }

        return trim($method.' '.$path);
    }
}
