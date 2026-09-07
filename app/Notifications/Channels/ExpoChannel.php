<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoChannel
{
    private const EXPO_API_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send the given notification to all registered devices of the notifiable.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toExpo')) {
            return;
        }

        $tokens = method_exists($notifiable, 'deviceTokens')
            ? $notifiable->deviceTokens()->pluck('token')->all()
            : [];

        if (empty($tokens)) {
            return;
        }

        // Validate Expo token format: ExponentPushToken[...] or ExpoPushToken[...]
        $validTokens = [];
        foreach ($tokens as $token) {
            if (is_string($token) && preg_match('/^(ExponentPushToken|ExpoPushToken)\[.*\]$/', $token)) {
                $validTokens[] = $token;
            } else {
                Log::warning("Skipping invalid Expo push token format [{$token}] for user ID [{$notifiable->id}]");
            }
        }

        if (empty($validTokens)) {
            return;
        }

        $baseMessage = $notification->toExpo($notifiable);

        if (empty($baseMessage)) {
            return;
        }

        // Default mobile settings if not explicitly provided
        $baseMessage['sound'] = $baseMessage['sound'] ?? 'default';
        $baseMessage['priority'] = $baseMessage['priority'] ?? 'high';
        $baseMessage['channelId'] = $baseMessage['channelId'] ?? 'deliveries';

        // Prepare batch messages (Expo API supports an array of messages)
        $messages = [];
        foreach ($validTokens as $token) {
            $msg = $baseMessage;
            $msg['to'] = $token;
            $messages[] = $msg;
        }

        try {
            $response = Http::withHeaders([
                'Accept'          => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
                'Content-Type'    => 'application/json',
            ])->timeout(5)->post(self::EXPO_API_URL, count($messages) === 1 ? $messages[0] : $messages);

            if ($response->failed()) {
                Log::error('Expo push notification HTTP request failed', [
                    'status'  => $response->status(),
                    'body'    => $response->json() ?? $response->body(),
                    'user_id' => $notifiable->id,
                ]);
            } else {
                $responseData = $response->json();
                $tickets = $responseData['data'] ?? [];

                // Normalize single ticket response to array if necessary
                if (isset($tickets['status'])) {
                    $tickets = [$tickets];
                }

                if (is_array($tickets)) {
                    foreach ($tickets as $index => $ticket) {
                        if (isset($ticket['status']) && $ticket['status'] === 'error') {
                            $failedToken = $validTokens[$index] ?? null;
                            Log::warning('Expo push notification ticket error', [
                                'ticket'  => $ticket,
                                'token'   => $failedToken,
                                'user_id' => $notifiable->id,
                            ]);

                            // Prune dead token on DeviceNotRegistered
                            if (($ticket['details']['error'] ?? '') === 'DeviceNotRegistered' && $failedToken) {
                                $notifiable->deviceTokens()->where('token', $failedToken)->delete();
                                Log::info("Pruned unregistered device token [{$failedToken}] for user ID [{$notifiable->id}]");
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Expo push notification exception: ' . $e->getMessage(), [
                'user_id' => $notifiable->id,
            ]);
        }
    }
}
