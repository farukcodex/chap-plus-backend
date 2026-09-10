<?php

namespace App\Notifications\Admin;

use App\Models\User;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewMerchantRegistrationNotification extends Notification
{
    use Queueable;

    public User $merchant;

    public function __construct(User $merchant)
    {
        $this->merchant = $merchant->loadMissing(['merchantProfile', 'roles']);
    }

    public function via(object $notifiable): array
    {
        return ['database', ExpoChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $profile = $this->merchant->merchantProfile;
        $businessName = $profile?->business_name ?? $this->merchant->name;
        $title = "New Merchant Registered 🏪";
        $message = "Merchant '{$businessName}' has registered and is awaiting verification.";

        return [
            'type'          => 'new_merchant_registration',
            'title'         => $title,
            'message'       => $message,
            'merchant_id'   => (int) $this->merchant->id,
            'business_name' => (string) $businessName,
            'email'         => (string) $this->merchant->email,
            'action_url'    => '/admin/merchants',
        ];
    }

    public function toExpo(object $notifiable): array
    {
        $data = $this->toDatabase($notifiable);

        return [
            'title'    => $data['title'],
            'body'     => $data['message'],
            'sound'    => 'default',
            'priority' => 'default',
            'data'     => [
                'type'        => 'new_merchant_registration',
                'merchant_id' => $data['merchant_id'],
                'url'         => $data['action_url'],
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
