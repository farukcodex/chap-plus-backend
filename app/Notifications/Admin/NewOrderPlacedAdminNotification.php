<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderPlacedAdminNotification extends Notification
{
    use Queueable;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing(['user', 'merchantProfile']);
    }

    public function via(object $notifiable): array
    {
        return ['database', ExpoChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $orderNumber = $this->order->order_number ?? ('#ORD-' . str_pad($this->order->id, 5, '0', STR_PAD_LEFT));
        $customerName = $this->order->user?->name ?? 'Customer';
        $merchantName = $this->order->merchantProfile?->business_name ?? 'Store';
        $totalAmount = (float) ($this->order->total_amount + ($this->order->delivery_fee ?? 0));
        $currency = $this->order->merchantProfile?->currency ?? 'KES';

        $title = "New Order Placed 📦";
        $message = "Order {$orderNumber} placed by {$customerName} for {$merchantName} ({$currency} " . number_format($totalAmount, 2) . ").";

        return [
            'type'         => 'new_order_placed',
            'title'        => $title,
            'message'      => $message,
            'order_id'     => (int) $this->order->id,
            'order_number' => (string) $orderNumber,
            'total_amount' => $totalAmount,
            'currency'     => $currency,
            'action_url'   => '/admin/orders-bookings',
        ];
    }

    public function toExpo(object $notifiable): array
    {
        $data = $this->toDatabase($notifiable);

        return [
            'title'    => $data['title'],
            'body'     => $data['message'],
            'sound'    => 'default',
            'priority' => 'high',
            'data'     => [
                'type'     => 'new_order_placed',
                'order_id' => $data['order_id'],
                'url'      => $data['action_url'],
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
