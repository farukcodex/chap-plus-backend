<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class AdminNotificationService
{
    /**
     * Send a notification to all active system administrators.
     */
    public static function notifyAdmins(Notification $notification): void
    {
        try {
            $admins = User::role('ADMIN')->get();
            if ($admins->isNotEmpty()) {
                NotificationFacade::send($admins, $notification);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch admin notification: ' . $e->getMessage(), [
                'notification' => get_class($notification),
                'exception'    => $e,
            ]);
        }
    }
}
