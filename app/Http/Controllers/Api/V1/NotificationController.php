<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $item->markAsRead();

        return ApiResponse::success($item);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::success(null, __('All notifications marked as read.'));
    }
}
