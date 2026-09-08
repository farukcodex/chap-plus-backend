<?php

namespace App\Notifications\Customer;

use App\Models\BusBooking;
use App\Notifications\Channels\ExpoChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusBookingPaymentFailedNotification extends Notification
{
    use Queueable;

    public BusBooking $booking;
    public ?string $reason;

    public function __construct(BusBooking $booking, ?string $reason = null)
    {
        $this->booking = $booking->loadMissing(['bus', 'user', 'merchantProfile']);
        $this->reason = $reason;
    }

    /**
     * Delivery channels: In-App Database Inbox + Email + Mobile Expo Push.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', ExpoChannel::class];
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

        $reasonText = $this->reason ? " ({$this->reason})." : ".";

        return [
            'type'         => 'bus_booking_payment_failed',
            'title'        => 'Payment Failed — Seats Released ⚠️',
            'message'      => "Payment for booking {$bookingCode} ({$busName}, Seats: {$seats} on {$travelDate}) was not completed{$reasonText} The reserved seats have been released.",
            'booking_id'   => $this->booking->id,
            'booking_code' => $bookingCode,
            'bus_name'     => $busName,
            'travel_date'  => $travelDate,
            'seat_numbers' => $this->booking->seat_numbers,
            'total_price'  => (float) $this->booking->total_price,
            'reason'       => $this->reason,
        ];
    }

    /**
     * Expo push notification payload.
     */
    public function toExpo(object $notifiable): array
    {
        $busName = $this->getBusName();
        $bookingCode = '#BUS-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT);

        return [
            'title'     => 'Payment Failed — Seats Released ⚠️',
            'body'      => "Payment for {$bookingCode} ({$busName}) failed or was cancelled. Reserved seats have been released.",
            'sound'     => 'default',
            'priority'  => 'high',
            'channelId' => 'bookings',
            'data'      => [
                'type'       => 'bus_booking_payment_failed',
                'booking_id' => $this->booking->id,
            ],
        ];
    }

    /**
     * Mail notification representation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $busName = $this->getBusName();
        $bookingCode = '#BUS-' . str_pad($this->booking->id, 5, '0', STR_PAD_LEFT);
        $merchantName = $this->booking->merchantProfile?->business_name ?? 'Bus Operator';

        $route = '';
        if ($this->booking->bus) {
            $from = $this->booking->bus->departure_place ?? $this->booking->bus->from_city ?? '';
            $to = $this->booking->bus->destination_place ?? $this->booking->bus->to_city ?? '';
            if ($from || $to) {
                $route = "{$from} → {$to}";
            }
        }

        $travelDate = $this->booking->travel_date?->format('D, M d, Y') ?? '';
        $seats = is_array($this->booking->seat_numbers) ? implode(', ', $this->booking->seat_numbers) : $this->booking->seat_numbers;

        return (new MailMessage)
            ->subject("Payment Failed — Bus Booking {$bookingCode}")
            ->view('emails.customer.bus_booking_failed', [
                'user'         => $notifiable,
                'busBooking'   => $this->booking,
                'busName'      => $busName,
                'merchantName' => $merchantName,
                'route'        => $route,
                'travelDate'   => $travelDate,
                'seats'        => $seats,
                'reason'       => $this->reason ?? 'Payment was cancelled or timed out.',
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
