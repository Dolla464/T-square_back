<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\Notification\NotificationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    // تعريف الـ Service
    protected NotificationService $notificationService;

    use ApiResponseTrait;

    // حقن الـ Service داخل الـ Controller
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * جلب كل الإشعارات
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // 1. جلب البيانات من الـ Service
        $notifications = $this->notificationService->getUserNotifications($user);
        $unreadCount = $this->notificationService->getUnreadCount($user);

        // 2. إرجاع الـ Resource للفرونت إند
        return NotificationResource::collection($notifications)->additional([
            'status' => 'success',
            'message' => 'Notifications retrieved successfully',
            'meta' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * تحديد إشعار معين كمقروء
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // الـ Service هترجع true لو لقت الإشعار وحددته، و false لو مش موجود
        $isMarked = $this->notificationService->markAsRead($user, $id);

        if (! $isMarked) {

            return $this->errorResponse('Notification not found', 404);
        }

        // 4. استخدام دالة النجاح من الـ Trait (البيانات null لأن مفيش داتا هترجع)
        return $this->successResponse(null, 'Notification marked as read');
    }

    /**
     * تحديد جميع الإشعارات كمقروءة
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->notificationService->markAllAsRead($user);

        return $this->successResponse(null, 'All notifications marked as read');
    }
}
