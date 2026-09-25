<?php

namespace App\Services\Admin;

use App\Http\Middleware\EnsureActivityLogVerified;
use App\Models\ActivityLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AdminActivityLogService
{
    public function index(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('user_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['role']), function ($query) use ($filters) {
                $query->where('role', $filters['role']);
            })
            ->when(! empty($filters['method']), function ($query) use ($filters) {
                $query->where('http_method', strtoupper($filters['method']));
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                $query->where('user_id', $filters['user_id']);
            })
            ->when(! empty($filters['date_from']), function ($query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function ($query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return array{token: string, expires_at: string}
     */
    public function issueVerificationToken(int $userId, int $ttlMinutes = 15): array
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes($ttlMinutes);

        cache()->put(
            EnsureActivityLogVerified::cacheKey($userId, $token),
            true,
            $expiresAt,
        );

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
