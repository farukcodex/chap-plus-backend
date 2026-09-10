<?php

namespace App\Notifications\Admin;

use App\Models\PayoutRequest;
use App\Models\PlatformSetting;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewPayoutRequestNotification extends Notification
{
    use Queueable;

    public PayoutRequest $payout;

    public function __construct(PayoutRequest $payout)
    {
        $this->payout = $payout->loadMissing(['user.roles', 'user.merchantProfile', 'user.riderProfile']);
    }

    public function via(object $notifiable): array
    {
        return ['database', ExpoChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $user = $this->payout->user;
        $name = $user?->name ?? 'User #' . $this->payout->user_id;
        $roleNames = $user ? $user->getRoleNames() : collect();
        $isMerchant = $roleNames->intersect(['ECOMMERCE_MERCHANT', 'RESTAURANT_MERCHANT', 'HOTEL_MERCHANT', 'BUS_MERCHANT'])->isNotEmpty();
        $accountType = $isMerchant ? 'Merchant' : ($roleNames->contains('RIDER') ? 'Rider' : 'User');

        $currency = (string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES');
        $amount = (float) $this->payout->amount;
        $formattedAmount = number_format($amount, 2);

        $title = "New Withdrawal Request 💰";
        $message = "{$name} ({$accountType}) requested a withdrawal of {$currency} {$formattedAmount} to {$this->payout->mpesa_number}.";

        return [
            'type'         => 'new_payout_request',
            'title'        => $title,
            'message'      => $message,
            'payout_id'    => (int) $this->payout->id,
            'user_id'      => (int) $this->payout->user_id,
            'account_type' => $accountType,
            'amount'       => $amount,
            'currency'     => $currency,
            'mpesa_number' => (string) $this->payout->mpesa_number,
            'action_url'   => '/admin/payouts',
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
                'type'      => 'new_payout_request',
                'payout_id' => $data['payout_id'],
                'url'       => $data['action_url'],
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
