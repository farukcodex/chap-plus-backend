<?php

namespace App\Notifications\Rider;

use App\Models\Order;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCancelledNotification extends Notification
{
    use Queueable;

    protected Order $order;
    protected ?string $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, ?string $reason = null)
    {
        $this->order = $order;
        $this->reason = $reason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', ExpoChannel::class];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $orderNumber = $this->order->order_number ?? ('#ORD-' . str_pad($this->order->id, 5, '0', STR_PAD_LEFT));
        $reasonText = $this->reason ? " Reason: {$this->reason}." : '';

        return [
            'type'         => 'order_cancelled',
            'title'        => 'Order Cancelled ⚠️',
            'message'      => "Order {$orderNumber} was cancelled.{$reasonText} Please return items if already picked up.",
            'order_id'     => $this->order->id,
            'order_number' => $orderNumber,
            'reason'       => $this->reason,
        ];
    }

    /**
     * Get the Expo push notification representation.
     *
     * @return array<string, mixed>
     */
    public function toExpo(object $notifiable): array
    {
        $orderNumber = $this->order->order_number ?? ('#ORD-' . str_pad($this->order->id, 5, '0', STR_PAD_LEFT));

        return [
            'title'    => 'Order Cancelled ⚠️',
            'body'     => "Order {$orderNumber} was cancelled. Please check delivery details.",
            'sound'    => 'default',
            'priority' => 'high',
            'data'     => [
                'type'         => 'order_cancelled',
                'order_id'     => $this->order->id,
                'order_number' => $orderNumber,
            ],
        ];
    }

    /**
     * Get the array representation of the notification (fallback).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
