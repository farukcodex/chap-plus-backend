<?php

namespace App\Notifications\Rider;

use App\Models\Order;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryCompletedNotification extends Notification
{
    use Queueable;

    protected Order $order;
    protected float $earnedAmount;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, float $earnedAmount)
    {
        $this->order = $order;
        $this->earnedAmount = $earnedAmount;
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
        $currency = $this->order->merchantProfile?->currency ?? 'USD';
        $orderNumber = $this->order->order_number ?? ('#ORD-' . str_pad($this->order->id, 5, '0', STR_PAD_LEFT));

        return [
            'type'          => 'delivery_completed',
            'title'         => 'Delivery Completed! 💰',
            'message'       => "Order {$orderNumber} delivered successfully. {$currency} " . number_format($this->earnedAmount, 2) . " has been credited to your wallet.",
            'order_id'      => $this->order->id,
            'order_number'  => $orderNumber,
            'earned_amount' => round($this->earnedAmount, 2),
            'currency'      => $currency,
        ];
    }

    /**
     * Get the Expo push notification representation.
     *
     * @return array<string, mixed>
     */
    public function toExpo(object $notifiable): array
    {
        $currency = $this->order->merchantProfile?->currency ?? 'USD';
        $orderNumber = $this->order->order_number ?? ('#ORD-' . str_pad($this->order->id, 5, '0', STR_PAD_LEFT));

        return [
            'title'    => 'Delivery Completed! 💰',
            'body'     => "Order {$orderNumber} delivered! {$currency} " . number_format($this->earnedAmount, 2) . " added to your wallet.",
            'sound'    => 'default',
            'priority' => 'high',
            'data'     => [
                'type'         => 'delivery_completed',
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
