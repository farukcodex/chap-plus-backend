<?php

namespace App\Notifications\Customer;

use App\Models\BusBooking;
use App\Notifications\Channels\ExpoChannel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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
            $from = $this->booking->bus->departure_place ?? $this->booking->bus->from_city ?? '';
            $to = $this->booking->bus->destination_place ?? $this->booking->bus->to_city ?? '';
            if ($from || $to) {
                $route = "{$from} → {$to}";
            }
        }

        $mail = (new MailMessage)
            ->subject("Bus Ticket Confirmation — {$busName} ({$bookingId})")
            ->view('emails.customer.bus_booking', [
                'user'         => $notifiable,
                'busBooking'   => $this->booking,
                'busName'      => $busName,
                'merchantName' => $merchantName,
                'route'        => $route,
            ]);

        // Generate and attach official PDF Boarding Pass
        try {
            $pdfContent = $this->generateTicketPdf($notifiable, $bookingId, $busName, $merchantName);
            $cleanCode = str_replace('#', '', $bookingId);
            $mail->attachData($pdfContent, "ChapPlus-Ticket-{$cleanCode}.pdf", [
                'mime' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to generate PDF ticket for Bus Booking #{$this->booking->id}: " . $e->getMessage());
        }

        return $mail;
    }

    /**
     * Generate printable PDF e-ticket with embedded QR Code
     */
    public function generateTicketPdf(object $notifiable, string $bookingId, string $busName, string $merchantName): string
    {
        $bus = $this->booking->bus;
        $seatNumbers = is_array($this->booking->seat_numbers) ? $this->booking->seat_numbers : [$this->booking->seat_numbers];

        $travelDateFormatted = $this->booking->travel_date
            ? Carbon::parse($this->booking->travel_date)->format('D, M d, Y')
            : date('D, M d, Y');

        $pdfData = [
            'booking'             => $this->booking,
            'bookingId'           => $bookingId,
            'busName'             => $busName,
            'merchantName'        => $merchantName,
            'fromCity'            => $bus?->from_city ?? $bus?->departure_place ?? 'Departure',
            'departurePlace'      => $bus?->departure_place ?? 'Terminal',
            'toCity'              => $bus?->to_city ?? $bus?->destination_place ?? 'Destination',
            'destinationPlace'    => $bus?->destination_place ?? 'Terminal',
            'departureTime'       => $bus?->departure_time ?? 'Scheduled',
            'destinationTime'     => $bus?->destination_time ?? 'Estimated',
            'journeyDuration'     => $bus?->journey_duration ?? 'Direct',
            'travelDateFormatted' => $travelDateFormatted,
            'passengerName'       => $this->booking->passenger_name ?? $notifiable->name ?? 'Passenger',
            'passengerPhone'      => $this->booking->passenger_phone ?? $notifiable->phone_number ?? '',
            'passengerEmail'      => $this->booking->passenger_email ?? $notifiable->email ?? '',
            'seatNumbers'         => $seatNumbers,
            'totalPrice'          => (float) $this->booking->total_price,
            'paymentMethod'       => $this->booking->payment_method ?? 'mpesa',
            'mpesaReceipt'        => $this->booking->mpesa_receipt_number ?? '',
            'issuedAt'            => now()->format('Y-m-d H:i:s T'),
        ];

        $pdf = Pdf::loadView('tickets.bus_ticket_pdf', $pdfData);
        return $pdf->output();
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
