<?php

namespace App\Notifications\Customer;

use App\Models\BusBooking;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusBookingConfirmedNotification extends Notification
{
    use Queueable;

    public BusBooking $booking;

    public function __construct(BusBooking $booking)
    {
        $this->booking = $booking->loadMissing(['bus', 'user', 'merchantProfile']);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $busName = $this->getBusName();
        $bookingId = '#BUS-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT);
        $travelDate = $this->booking->travel_date?->format('M d, Y') ?? '';
        $seats = is_array($this->booking->seat_numbers) ? implode(', ', $this->booking->seat_numbers) : $this->booking->seat_numbers;

        return [
            'type'         => 'bus_booking_confirmed',
            'title'        => 'Bus Ticket Confirmed! 🚌',
            'message'      => "Your ticket for {$busName} on {$travelDate} (Seats: {$seats}) is confirmed. Booking: {$bookingId}",
            'booking_id'   => $this->booking->id,
            'bus_name'     => $busName,
            'travel_date'  => $travelDate,
            'seat_numbers' => $this->booking->seat_numbers,
            'total_price'  => (float) $this->booking->total_price,
        ];
    }

    public function toExpo(object $notifiable): array
    {
        $busName = $this->getBusName();
        $travelDate = $this->booking->travel_date?->format('M d, Y') ?? '';
        $seats = is_array($this->booking->seat_numbers) ? implode(', ', $this->booking->seat_numbers) : $this->booking->seat_numbers;

        return [
            'title'    => 'Bus Ticket Confirmed! 🚌',
            'body'     => "Seat(s) {$seats} confirmed on {$busName} for {$travelDate}!",
            'sound'    => 'default',
            'priority' => 'high',
            'channelId'=> 'bookings',
            'data'     => [
                'type'       => 'bus_booking_confirmed',
                'booking_id' => $this->booking->id,
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $busName = $this->getBusName();
        $bookingId = '#BUS-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT);
        $merchantName = $this->booking->merchantProfile?->business_name ?? 'Bus Operator';

        $route = '';
        if ($this->booking->bus) {
            $from = $this->booking->bus->from_city ?? '';
            $to = $this->booking->bus->to_city ?? '';
            if ($from || $to) {
                $route = "{$from} → {$to}";
            }
        }

        return (new MailMessage)
            ->subject("Bus Ticket Confirmation — {$busName} ({$bookingId})")
            ->view('emails.customer.bus_booking', [
                'user'         => $notifiable,
                'busBooking'   => $this->booking,
                'busName'      => $busName,
                'merchantName' => $merchantName,
                'route'        => $route,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function getBusName(): string
    {
        return $this->booking->bus?->name ?? 'Bus';
    }
}
