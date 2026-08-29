<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request
            ->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,

            'unread_count' =>
                $request
                    ->user()
                    ->unreadNotifications()
                    ->count(),

            'data' => $notifications,
        ]);
    }


    public function markAsRead(
        Request $request,
        string $notificationId
    ) {
        $notification = $request
            ->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' =>
                'Notification marked as read.',
        ]);
    }


    public function markAllAsRead(
        Request $request
    ) {
        $request
            ->user()
            ->unreadNotifications
            ->markAsRead();

        return response()->json([
            'success' => true,
            'message' =>
                'All notifications marked as read.',
        ]);
    }
}
