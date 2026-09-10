<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get the authenticated admin user.
     */
    protected function getAdminUser(Request $request)
    {
        return $request->user() ?? auth()->user();
    }

    /**
     * Get paginated notifications for the authenticated admin with unread badge count.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->getAdminUser($request);
        if (!$admin) {
            return $this->apiError('Unauthenticated', 401);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $filter = $request->query('filter'); // 'unread' or null/'all'

        $query = $admin->notifications();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        if ($request->filled('type')) {
            $type = $request->query('type');
            $query->where('data->type', $type);
        }

        $paginated = $query->latest()->paginate($perPage);
        $unreadCount = (int) $admin->unreadNotifications()->count();
        $totalCount = (int) $admin->notifications()->count();

        $paginated->through(function ($item) {
            $data = is_array($item->data) ? $item->data : (json_decode($item->data, true) ?? []);

            return [
                'id'         => (string) $item->id,
                'type'       => (string) ($data['type'] ?? class_basename($item->type)),
                'title'      => (string) ($data['title'] ?? 'Admin Notification'),
                'message'    => (string) ($data['message'] ?? ''),
                'action_url' => (string) ($data['action_url'] ?? ''),
                'data'       => $data,
                'read'       => $item->read_at !== null,
                'read_at'    => $item->read_at?->toIso8601String(),
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return $this->apiSuccess('Admin notifications retrieved successfully', [
            'unread_count'  => $unreadCount,
            'total_count'   => $totalCount,
            'notifications' => $paginated,
        ]);
    }

    /**
     * Get unread badge count for header/navbar bell icon.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $admin = $this->getAdminUser($request);
        if (!$admin) {
            return $this->apiError('Unauthenticated', 401);
        }

        $unreadCount = (int) $admin->unreadNotifications()->count();

        return $this->apiSuccess('Unread notifications count retrieved', [
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * View details of a specific notification (auto-marks as read).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $admin = $this->getAdminUser($request);
        if (!$admin) {
            return $this->apiError('Unauthenticated', 401);
        }

        $notification = $admin->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->apiError('Notification not found', 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $data = is_array($notification->data) ? $notification->data : (json_decode($notification->data, true) ?? []);

        return $this->apiSuccess('Notification details retrieved successfully', [
            'notification' => [
                'id'         => (string) $notification->id,
                'type'       => (string) ($data['type'] ?? class_basename($notification->type)),
                'title'      => (string) ($data['title'] ?? 'Admin Notification'),
                'message'    => (string) ($data['message'] ?? ''),
                'action_url' => (string) ($data['action_url'] ?? ''),
                'data'       => $data,
                'read'       => true,
                'read_at'    => $notification->fresh()->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ],
            'unread_count' => (int) $admin->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $admin = $this->getAdminUser($request);
        if (!$admin) {
            return $this->apiError('Unauthenticated', 401);
        }

        $notification = $admin->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->apiError('Notification not found', 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $this->apiSuccess('Notification marked as read', [
            'id'           => $notification->id,
            'read_at'      => $notification->fresh()->read_at?->toIso8601String(),
            'unread_count' => (int) $admin->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all unread notifications of the admin as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $admin = $this->getAdminUser($request);
        if (!$admin) {
            return $this->apiError('Unauthenticated', 401);
        }

        $admin->unreadNotifications->markAsRead();

        return $this->apiSuccess('All notifications marked as read', [
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a specific notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $admin = $this->getAdminUser($request);
        if (!$admin) {
            return $this->apiError('Unauthenticated', 401);
        }

        $notification = $admin->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->apiError('Notification not found', 404);
        }

        $notification->delete();

        return $this->apiSuccess('Notification deleted successfully', [
            'id'           => $id,
            'unread_count' => (int) $admin->unreadNotifications()->count(),
        ]);
    }

    /**
     * Delete/Clear all notifications for the authenticated admin.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $admin = $this->getAdminUser($request);
        if (!$admin) {
            return $this->apiError('Unauthenticated', 401);
        }

        $admin->notifications()->delete();

        return $this->apiSuccess('All notifications cleared successfully', [
            'unread_count' => 0,
            'total_count'  => 0,
        ]);
    }
}
