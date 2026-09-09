<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutStatusUpdatedNotification extends Notification
{
    use Queueable;

    public PayoutRequest $payout;

    public function __construct(PayoutRequest $payout)
    {
        $this->payout = $payout;
    }

    public function via(object $notifiable): array
    {
        return ['database', ExpoChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $content = $this->getStatusContent();

        return [
            'type'                  => 'payout_status_updated',
            'payout_id'             => $this->payout->id,
            'status'                => $this->payout->status,
            'amount'                => (float) $this->payout->amount,
            'payment_mode'          => $this->payout->payment_mode,
            'transaction_reference' => $this->payout->transaction_reference,
            'title'                 => $content['title'],
            'message'               => $content['message'],
        ];
    }

    public function toExpo(object $notifiable): array
    {
        $content = $this->getStatusContent();

        return [
            'title' => $content['title'],
            'body'  => $content['message'],
            'data'  => [
                'type'      => 'payout_status_updated',
                'payout_id' => $this->payout->id,
                'status'    => $this->payout->status,
            ],
        ];
    }

    protected function getStatusContent(): array
    {
        $amount = number_format((float) $this->payout->amount, 2);

        switch ($this->payout->status) {
            case 'completed':
                $ref = $this->payout->transaction_reference ? " (Ref: {$this->payout->transaction_reference})" : '';
                return [
                    'title'   => 'Payout Completed',
                    'message' => "Your payout request of KES {$amount} has been paid successfully{$ref}.",
                ];

            case 'processing':
                return [
                    'title'   => 'Payout Processing',
                    'message' => "Your automated payout of KES {$amount} is currently processing to {$this->payout->mpesa_number}.",
                ];

            case 'rejected':
                $reason = $this->payout->rejection_reason ? ": {$this->payout->rejection_reason}" : '.';
                return [
                    'title'   => 'Payout Request Rejected',
                    'message' => "Your payout request of KES {$amount} was rejected{$reason} The funds have been refunded to your wallet.",
                ];

            default:
                return [
                    'title'   => 'Payout Status Updated',
                    'message' => "Your payout request of KES {$amount} is now {$this->payout->status}.",
                ];
        }
    }
}
