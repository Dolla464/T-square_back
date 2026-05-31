<?php

namespace App\Services\Admin;

use App\Models\Message;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AdminMessageService
{
    /**
     * Return a paginated list of messages with optional text search and date filtering.
     *
     * @param  int                  $perPage
     * @param  array<string, mixed> $filters  Supported keys: search, date_filter
     */
    public function index(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return Message::query()
            ->when(
                !empty($filters['search']),
                function ($query) use ($filters): void {
                    $term = $filters['search'];

                    // Group OR conditions so they don't bleed into other ->where() clauses.
                    $query->where(function ($q) use ($term): void {
                        $q->where('name',    'like', "%{$term}%")
                          ->orWhere('title',   'like', "%{$term}%")
                          ->orWhere('content', 'like', "%{$term}%");
                    });
                }
            )
            ->when(
                !empty($filters['date_filter']),
                function ($query) use ($filters): void {
                    $query->where('created_at', '>=', $this->resolveDateFilter($filters['date_filter']));
                }
            )
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Return a single message (Route Model Binding passes the already-resolved instance).
     */
    public function show(Message $message): Message
    {
        return $message;
    }

    /**
     * Translate a date_filter string into a Carbon cut-off date.
     */
    private function resolveDateFilter(string $filter): Carbon
    {
        return match ($filter) {
            'last_week'     => Carbon::now()->subDays(7),
            'last_month'    => Carbon::now()->subDays(30),
            'last_3_months' => Carbon::now()->subDays(90),
        };
    }
}
