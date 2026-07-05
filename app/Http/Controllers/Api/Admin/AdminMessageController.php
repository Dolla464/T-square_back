<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexMessageRequest;
use App\Http\Resources\Admin\MessageCollection;
use App\Http\Resources\Admin\MessageShowResource;
use App\Models\Message;
use App\Services\Admin\AdminMessageService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Admin: Messages
 */
class AdminMessageController extends Controller
{
    public function __construct(
        private readonly AdminMessageService $messageService,
    ) {}

    /**
     * Display a paginated listing of messages.
     *
     * Supported query parameters:
     *   - search        (string)  – filters name, title, and content with LIKE
     *   - date_filter   (string)  – last_week | last_month | last_3_months
     *   - per_page      (int)     – items per page, default 10
     */
    public function index(IndexMessageRequest $request): MessageCollection
    {
        $filters  = $request->only(['search', 'date_filter']);
        $perPage  = (int) $request->query('per_page', 10);

        $messages = $this->messageService->index($perPage, $filters);

        return new MessageCollection($messages);
    }

    /**
     * Display the specified message.
     * Laravel resolves {message} via Route Model Binding automatically.
     */
    public function show(Message $message): JsonResponse
    {
        $message = $this->messageService->show($message);

        return $this->successResponse(
            new MessageShowResource($message),
            'Message retrieved successfully'
        );
    }

    /**
     * Remove the specified message from storage.
     */
    public function destroy(Message $message): JsonResponse
    {
        $this->messageService->destroy($message);

        return $this->successResponse(null, 'Message deleted successfully');
    }
}
