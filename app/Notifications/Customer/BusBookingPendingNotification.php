<?php

namespace App\Notifications\Customer;

use App\Models\BusBooking;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BusBookingPendingNotification extends Notification
{
    use Queueable;

    public BusBooking $booking;

    public function __construct(BusBooking $booking)
    {
        $this->booking = $booking->loadMissing(['bus', 'user', 'merchantProfile']);
    }

    /**
     * Delivery channels: In-App Database Inbox + Mobile Expo Push.
     */
    public function via(object $notifiable): array
    {
        return ['database', ExpoChannel::class];
    }

    /**
     * Database (In-App inbox) payload.
     */
    public function toDatabase(object $notifiable): array
    {
        $busName = $this->getBusName();
        $bookingCode = '#BUS-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT);
        $travelDate = $this->booking->travel_date?->format('M d, Y') ?? '';
        $seats = is_array($this->booking->seat_numbers) ? implode(', ', $this->booking->seat_numbers) : $this->booking->seat_numbers;

        return [
            'type'         => 'bus_booking_pending',
            'title'        => 'Seats Reserved — Payment Pending ⏳',
            'message'      => "Your seats ({$seats}) on {$busName} for {$travelDate} are reserved for 15 minutes. Complete payment to secure your ticket ({$bookingCode}).",
            'booking_id'   => $this->booking->id,
            'booking_code' => $bookingCode,
            'bus_name'     => $busName,
            'travel_date'  => $travelDate,
            'seat_numbers' => $this->booking->seat_numbers,
            'total_price'  => (float) $this->booking->total_price,
            'locked_until' => $this->booking->locked_until?->toIso8601String(),
        ];
    }

    /**
     * Expo push notification payload.
     */
    public function toExpo(object $notifiable): array
    {
        $busName = $this->getBusName();
        $seats = is_array($this->booking->seat_numbers) ? implode(', ', $this->booking->seat_numbers) : $this->booking->seat_numbers;

        return [
            'title'     => 'Seats Reserved — Payment Pending ⏳',
            'body'      => "Seat(s) {$seats} on {$busName} are reserved for 15 mins. Enter your M-Pesa PIN to confirm!",
            'sound'     => 'default',
            'priority'  => 'high',
            'channelId' => 'bookings',
            'data'      => [
                'type'       => 'bus_booking_pending',
                'booking_id' => $this->booking->id,
            ],
        ];
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
