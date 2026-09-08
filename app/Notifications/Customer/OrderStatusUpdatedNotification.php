<?php

namespace App\Notifications\Customer;

use App\Models\Order;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification
{
    use Queueable;

    public Order $order;
    public string $status;
    public ?string $cancellationReason;

    public function __construct(Order $order, string $status, ?string $cancellationReason = null)
    {
        $this->order = $order->loadMissing(['merchantProfile', 'rider', 'address', 'user']);
        $this->status = $status;
        $this->cancellationReason = $cancellationReason ?? $order->cancellation_reason;
    }

    /**
     * Get the notification's delivery channels.
     * Intermediate updates use Push + In-App to avoid email spam.
     * Delivered and Cancelled trigger Email + Push + In-App.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', ExpoChannel::class];

        if (in_array($this->status, ['delivered', 'cancelled'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the database (in-app) representation.
     */
    public function toDatabase(object $notifiable): array
    {
        $content = $this->getStatusContent();

        return [
            'type'            => 'order_status_updated',
            'status'          => $this->status,
            'title'           => $content['title'],
            'message'         => $content['message'],
            'order_id'        => $this->order->id,
            'order_number'    => $this->getOrderNumber(),
            'merchant_name'   => $this->getMerchantName(),
            'rider_name'      => $this->getRiderName(),
            'delivery_otp'    => $this->status === 'on_the_way' ? $this->order->delivery_otp : null,
        ];
    }

    /**
     * Get the Expo push notification representation.
     */
    public function toExpo(object $notifiable): array
    {
        $content = $this->getStatusContent();
        $orderNumber = $this->getOrderNumber();

        return [
            'title'    => $content['title'],
            'body'     => $content['message'],
            'sound'    => $this->status === 'delivered' ? 'default' : 'default',
            'priority' => 'high',
            'channelId'=> 'orders',
            'data'     => [
                'type'         => 'order_status_updated',
                'status'       => $this->status,
                'order_id'     => $this->order->id,
                'order_number' => $orderNumber,
                'delivery_otp' => $this->status === 'on_the_way' ? $this->order->delivery_otp : null,
            ],
        ];
    }

    /**
     * Get the mail representation (for delivered and cancelled).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->getStatusContent();
        $orderNumber = $this->getOrderNumber();
        $isDelivered = $this->status === 'delivered';

        $subject = $isDelivered
            ? "Order Delivered — {$orderNumber} 🍽️"
            : "Order Cancelled — {$orderNumber}";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.customer.order_status', [
                'user'                => $notifiable,
                'order'               => $this->order,
                'orderNumber'         => $orderNumber,
                'title'               => $content['title'],
                'statusMessage'       => $content['message'],
                'merchantName'        => $this->getMerchantName(),
                'riderName'           => $this->getRiderName(),
                'isDelivered'         => $isDelivered,
                'cancellationReason'  => $this->cancellationReason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function getStatusContent(): array
    {
        $orderNumber = $this->getOrderNumber();
        $merchantName = $this->getMerchantName();
        $riderName = $this->getRiderName();

        return match ($this->status) {
            'processing' => [
                'title'   => 'Order Being Prepared 👨‍🍳',
                'message' => "{$merchantName} has accepted Order {$orderNumber} and is preparing it.",
            ],
            'ready_for_pickup' => [
                'title'   => 'Order Ready for Pickup 📦',
                'message' => "Order {$orderNumber} is ready! We are assigning a nearby rider.",
            ],
            'accepted' => [
                'title'   => 'Rider Assigned 🛵',
                'message' => "Rider {$riderName} has accepted your delivery for Order {$orderNumber}.",
            ],
            'picked_up' => [
                'title'   => 'Order Picked Up 🛵',
                'message' => "Rider {$riderName} collected your order from {$merchantName} and is on the way.",
            ],
            'on_the_way' => [
                'title'   => 'Out for Delivery 🚀',
                'message' => "Order {$orderNumber} is on its way! Share delivery OTP {$this->order->delivery_otp} with rider upon arrival.",
            ],
            'delivered' => [
                'title'   => 'Order Delivered! 🍽️',
                'message' => "Order {$orderNumber} has been delivered. Enjoy your meal/items!",
            ],
            'cancelled' => [
                'title'   => 'Order Cancelled ✕',
                'message' => "Order {$orderNumber} was cancelled." . ($this->cancellationReason ? " Reason: {$this->cancellationReason}" : ''),
            ],
            default => [
                'title'   => "Order Update ({$this->status})",
                'message' => "Your order {$orderNumber} status changed to {$this->status}.",
            ],
        };
    }

    protected function getOrderNumber(): string
    {
        return $this->order->order_number ?? ('#ORD-' . str_pad($this->order->id, 5, '0', STR_PAD_LEFT));
    }

    protected function getMerchantName(): string
    {
        return $this->order->merchantProfile?->business_name ?? 'Store';
    }

    protected function getRiderName(): string
    {
        return $this->order->rider?->name ?? 'Delivery Partner';
    }
}
