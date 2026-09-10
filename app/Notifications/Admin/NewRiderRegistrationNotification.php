<?php

namespace App\Notifications\Admin;

use App\Models\User;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewRiderRegistrationNotification extends Notification
{
    use Queueable;

    public User $rider;

    public function __construct(User $rider)
    {
        $this->rider = $rider->loadMissing(['riderProfile']);
    }

    public function via(object $notifiable): array
    {
        return ['database', ExpoChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $profile = $this->rider->riderProfile;
        $name = $this->rider->name;
        $phone = $profile?->phone_number ?? ($this->rider->phone ?? 'N/A');

        $title = "New Rider Application 🛵";
        $message = "Rider '{$name}' ({$phone}) has submitted onboarding documents for verification.";

        return [
            'type'       => 'new_rider_registration',
            'title'      => $title,
            'message'    => $message,
            'rider_id'   => (int) $this->rider->id,
            'name'       => (string) $name,
            'email'      => (string) $this->rider->email,
            'phone'      => (string) $phone,
            'action_url' => '/admin/riders',
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
                'type'     => 'new_rider_registration',
                'rider_id' => $data['rider_id'],
                'url'      => $data['action_url'],
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
