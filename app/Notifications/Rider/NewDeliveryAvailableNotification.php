<?php

namespace App\Notifications\Rider;

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewDeliveryAvailableNotification extends Notification
{
    use Queueable;

    protected Order $order;
    protected float $earnings;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, ?float $earnings = null)
    {
        $this->order = $order;

        if ($earnings !== null) {
            $this->earnings = $earnings;
        } else {
            $deliveryFee = (float) ($order->delivery_fee ?? 0);
            $commissionPercent = (float) (PlatformSetting::where('key', 'rider_commission_percent')->value('value') ?? 0.00);
            $this->earnings = max(0, $deliveryFee - ($deliveryFee * ($commissionPercent / 100)));
        }
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

        return [
            'type'            => 'new_delivery_available',
            'title'           => 'New Delivery Available! 🛵',
            'message'         => "Order {$orderNumber} is ready for pickup from {$this->getMerchantName()}. Earn {$this->getCurrency()} " . number_format($this->earnings, 2),
            'order_id'        => $this->order->id,
            'order_number'    => $orderNumber,
            'rider_earnings'  => round($this->earnings, 2),
            'currency'        => $this->getCurrency(),
            'pickup_address'  => $this->order->merchantProfile?->address ?? '',
            'dropoff_address' => $this->order->address?->address ?? '',
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
            'title'    => 'New Delivery Available! 🛵',
            'body'     => "Order {$orderNumber} ready for pickup at {$this->getMerchantName()}. Earn {$this->getCurrency()} " . number_format($this->earnings, 2),
            'sound'    => 'default',
            'priority' => 'high',
            'data'     => [
                'type'         => 'new_delivery_available',
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

    protected function getMerchantName(): string
    {
        return $this->order->merchantProfile?->business_name ?? 'Restaurant';
    }

    protected function getCurrency(): string
    {
        return $this->order->merchantProfile?->currency ?? 'USD';
    }
}
