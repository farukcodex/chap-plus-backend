<?php

namespace App\Notifications\Customer;

use App\Models\Order;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing(['items.product', 'items.variant', 'merchantProfile', 'address', 'user']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoChannel::class];
    }

    /**
     * Get the database (in-app) representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $orderNumber = $this->getOrderNumber();
        $currency = $this->getCurrency();
        $grandTotal = (float) ($this->order->total_amount + $this->order->delivery_fee);
        $merchantName = $this->getMerchantName();

        return [
            'type'            => 'order_placed',
            'title'           => 'Order Confirmed! 🎉',
            'message'         => "Order {$orderNumber} is confirmed ({$currency} " . number_format($grandTotal, 2) . "). {$merchantName} will prepare it shortly.",
            'order_id'        => $this->order->id,
            'order_number'    => $orderNumber,
            'total_amount'    => $grandTotal,
            'currency'        => $currency,
            'merchant_name'   => $merchantName,
            'delivery_address'=> $this->order->address?->address ?? '',
        ];
    }

    /**
     * Get the Expo push notification representation.
     *
     * @return array<string, mixed>
     */
    public function toExpo(object $notifiable): array
    {
        $orderNumber = $this->getOrderNumber();
        $merchantName = $this->getMerchantName();

        return [
            'title'    => 'Order Confirmed! 🎉',
            'body'     => "Order {$orderNumber} confirmed! {$merchantName} will prepare your items soon.",
            'sound'    => 'default',
            'priority' => 'high',
            'channelId'=> 'orders',
            'data'     => [
                'type'         => 'order_placed',
                'order_id'     => $this->order->id,
                'order_number' => $orderNumber,
            ],
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $orderNumber = $this->getOrderNumber();
        $merchantName = $this->getMerchantName();
        $currency = $this->getCurrency();

        $items = $this->order->items->map(function ($item) {
            return [
                'name'     => $item->product?->name ?? 'Product',
                'variant'  => $item->variant?->name ?? null,
                'quantity' => $item->quantity,
                'price'    => (float) $item->price_at_time_of_purchase,
            ];
        })->toArray();

        return (new MailMessage)
            ->subject("Order Confirmation — {$orderNumber}")
            ->view('emails.customer.order_placed', [
                'user'            => $notifiable,
                'order'           => $this->order,
                'orderNumber'     => $orderNumber,
                'merchantName'    => $merchantName,
                'currency'        => $currency,
                'items'           => $items,
                'deliveryAddress' => $this->order->address?->address ?? '',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function getOrderNumber(): string
    {
        return $this->order->order_number ?? ('#ORD-' . str_pad($this->order->id, 5, '0', STR_PAD_LEFT));
    }

    protected function getMerchantName(): string
    {
        return $this->order->merchantProfile?->business_name ?? 'Store';
    }

    protected function getCurrency(): string
    {
        return $this->order->merchantProfile?->currency ?? 'USD';
    }
}
