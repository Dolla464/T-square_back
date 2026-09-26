<?php

namespace App\Services\Notification;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationService
{
    public const DEFAULT_PER_PAGE = 30;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        private NotificationDataEnricher $notificationDataEnricher,
    ) {}

    /**
     * جلب إشعارات المستخدم مع التقسيم (Pagination)
     */
    public function getUserNotifications(object $user, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $paginator = $user->notifications()->latest('created_at')->paginate($perPage);

        $this->notificationDataEnricher->enrich(Collection::make($paginator->items()));

        return $paginator;
    }

    /**
     * جلب عدد الإشعارات غير المقروءة
     */
    public function getUnreadCount(object $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * تحديد إشعار معين كمقروء
     */
    public function markAsRead(object $user, string $notificationId): bool
    {
        $notification = $user->notifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();

            return true;
        }

        return false;
    }

    /**
     * تحديد كل الإشعارات كمقروءة دفعة واحدة
     */
    public function markAllAsRead(object $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
