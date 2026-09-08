<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Register or update the user's Expo push token (Rider, Customer, Merchant, Admin).
     */
    public function updatePushToken(Request $request): JsonResponse
    {
        $request->validate([
            'expo_push_token' => 'required|string|max:255',
            'platform'        => 'nullable|string|in:android,ios,web',
            'device_name'     => 'nullable|string|max:100',
            'device_id'       => 'nullable|string|max:255',
        ]);

        $token = trim($request->expo_push_token);

        // Basic validation for Expo push token format
        if (!preg_match('/^(ExponentPushToken|ExpoPushToken)\[.*\]$/', $token)) {
            return $this->apiError('Invalid Expo push token format. Expected ExponentPushToken[...] or ExpoPushToken[...]', 422);
        }

        $user = $request->user();

        // If the token was previously tied to another user on the same device,
        // updateOrCreate transfers ownership to the current user.
        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id'      => $user->id,
                'platform'     => $request->platform,
                'device_name'  => $request->device_name,
                'device_id'    => $request->device_id,
                'last_used_at' => now(),
            ]
        );

        return $this->apiSuccess('Expo push token registered successfully', [
            'device_token' => [
                'id'           => $deviceToken->id,
                'token'        => $deviceToken->token,
                'platform'     => $deviceToken->platform,
                'device_name'  => $deviceToken->device_name,
                'device_id'    => $deviceToken->device_id,
                'last_used_at' => $deviceToken->last_used_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Remove the user's Expo push token (e.g., on logout).
     * If expo_push_token is provided, only that specific device's token is deleted.
     * If omitted, all device tokens for the user are removed.
     */
    public function removePushToken(Request $request): JsonResponse
    {
        $request->validate([
            'expo_push_token' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if ($request->filled('expo_push_token')) {
            $user->deviceTokens()->where('token', trim($request->expo_push_token))->delete();
            return $this->apiSuccess('Device token removed successfully');
        }

        $user->deviceTokens()->delete();

        return $this->apiSuccess('All device tokens removed successfully');
    }

    /**
     * Get paginated notifications for the authenticated user with unread badge count.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 15);
        $filter = $request->query('filter'); // optional: 'unread'

        $query = $user->notifications();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $paginated = $query->latest()->paginate($perPage);

        $unreadCount = $user->unreadNotifications()->count();

        $formatted = $paginated->through(function ($item) {
            $data = is_array($item->data) ? $item->data : (json_decode($item->data, true) ?? []);

            return [
                'id'         => $item->id,
                'type'       => $data['type'] ?? class_basename($item->type),
                'title'      => $data['title'] ?? 'Notification',
                'message'    => $data['message'] ?? '',
                'order_id'   => $data['order_id'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'data'       => $data,
                'read'       => $item->read_at !== null,
                'read_at'    => $item->read_at?->toISOString(),
                'created_at' => $item->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status'       => 'success',
            'message'      => 'Notifications retrieved successfully',
            'unread_count' => $unreadCount,
            'data'         => $formatted->items(),
            'pagination'   => [
                'total'        => $paginated->total(),
                'count'        => $paginated->count(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'total_pages'  => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * View/Show details of a specific notification.
     * Automatically marks the notification as read upon viewing.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->apiError('Notification not found', 404);
        }

        // Auto mark as read on view if not already read
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $data = is_array($notification->data) ? $notification->data : (json_decode($notification->data, true) ?? []);

        return $this->apiSuccess('Notification details retrieved successfully', [
            'notification' => [
                'id'           => $notification->id,
                'type'         => $data['type'] ?? class_basename($notification->type),
                'title'        => $data['title'] ?? 'Notification',
                'message'      => $data['message'] ?? '',
                'order_id'     => $data['order_id'] ?? null,
                'order_number' => $data['order_number'] ?? null,
                'booking_id'   => $data['booking_id'] ?? null,
                'data'         => $data,
                'read'         => true,
                'read_at'      => $notification->fresh()->read_at?->toISOString(),
                'created_at'   => $notification->created_at?->toISOString(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->apiError('Notification not found', 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $this->apiSuccess('Notification marked as read', [
            'id'           => $notification->id,
            'read_at'      => $notification->fresh()->read_at?->toISOString(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all unread notifications of the user as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return $this->apiSuccess('All notifications marked as read', [
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a specific notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->apiError('Notification not found', 404);
        }

        $notification->delete();

        return $this->apiSuccess('Notification deleted successfully', [
            'id'           => $id,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}
