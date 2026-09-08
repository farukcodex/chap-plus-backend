<?php

namespace App\Notifications\Customer;

use App\Models\HotelBooking;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HotelBookingConfirmedNotification extends Notification
{
    use Queueable;

    public HotelBooking $booking;

    public function __construct(HotelBooking $booking)
    {
        $this->booking = $booking->loadMissing(['hotel', 'user', 'merchantProfile']);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $hotelName = $this->getHotelName();
        $bookingId = '#HTL-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT);
        $checkIn = $this->booking->check_in_date?->format('M d, Y') ?? '';

        return [
            'type'        => 'hotel_booking_confirmed',
            'title'       => 'Hotel Booking Confirmed! 🏨',
            'message'     => "Your stay at {$hotelName} is confirmed for {$checkIn}. Booking: {$bookingId}",
            'booking_id'  => $this->booking->id,
            'hotel_name'  => $hotelName,
            'check_in'    => $checkIn,
            'total_price' => (float) $this->booking->total_price,
        ];
    }

    public function toExpo(object $notifiable): array
    {
        $hotelName = $this->getHotelName();
        $checkIn = $this->booking->check_in_date?->format('M d, Y') ?? '';

        return [
            'title'    => 'Hotel Booking Confirmed! 🏨',
            'body'     => "Your reservation at {$hotelName} is confirmed for {$checkIn}!",
            'sound'    => 'default',
            'priority' => 'high',
            'channelId'=> 'bookings',
            'data'     => [
                'type'       => 'hotel_booking_confirmed',
                'booking_id' => $this->booking->id,
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hotelName = $this->getHotelName();
        $bookingId = '#HTL-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT);

        return (new MailMessage)
            ->subject("Hotel Booking Confirmation — {$hotelName} ({$bookingId})")
            ->view('emails.customer.hotel_booking', [
                'user'         => $notifiable,
                'booking'      => $this->booking,
                'hotelName'    => $hotelName,
                'hotelAddress' => $this->booking->hotel?->address ?? '',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function getHotelName(): string
    {
        return $this->booking->hotel?->name ?? 'Hotel';
    }
}
