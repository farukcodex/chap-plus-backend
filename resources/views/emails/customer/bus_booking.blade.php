@extends('emails.layouts.app')

@section('title', config('app.name', 'ChapPlus') . ' — Bus Boarding Pass')
@section('accent_color', '#557116')

@section('content')
    <!-- Status Badge -->
    <div style="margin-bottom: 12px;">
        @include('emails.partials.badge', [
            'type' => 'success',
            'icon' => '🚌',
            'text' => 'Bus Ticket Confirmed'
        ])
    </div>

    <!-- Heading & Greeting -->
    <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.3px;">
        Ready for Departure!
    </h1>
    <p style="margin: 0 0 24px; font-size: 14px; color: #4b5563; line-height: 1.6;">
        Hi <strong>{{ $user->name }}</strong>, your ticket for <strong>{{ $busName }}</strong> is confirmed. Please arrive at the departure terminal at least 30 minutes before departure.
    </p>

    <!-- Boarding Pass Ticket Stub Box -->
    <table width="100%" cellpadding="16" cellspacing="0" border="0" style="background-color: #fcfdfa; border: 1.5px dashed #557116; border-radius: 12px; margin-bottom: 24px;">
        <tr>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Booking Code:<br>
                <strong style="font-size: 16px; color: #111827; letter-spacing: 0.5px;">#BUS-{{ str_pad($busBooking->id, 5, '0', STR_PAD_LEFT) }}</strong>
            </td>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Reserved Seat(s):<br>
                <strong style="font-size: 18px; color: #F86B17; font-weight: 800;">
                    💺 {{ is_array($busBooking->seat_numbers) ? implode(', ', $busBooking->seat_numbers) : $busBooking->seat_numbers }}
                </strong>
            </td>
        </tr>
        <tr>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Travel Date:<br>
                <strong style="font-size: 14px; color: #111827;">
                    📅 {{ \Carbon\Carbon::parse($busBooking->travel_date)->format('D, M d, Y') }}
                </strong>
            </td>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Bus Operator:<br>
                <strong style="font-size: 14px; color: #111827;">
                    {{ $merchantName }}
                </strong>
            </td>
        </tr>
        @if(!empty($route))
        <tr>
            <td colspan="2" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                Route / Journey:<br>
                <strong style="font-size: 14px; color: #111827;">🛣️ {{ $route }}</strong>
            </td>
        </tr>
        @endif
    </table>

    <!-- Payment Summary Table -->
    <table width="100%" cellpadding="8" cellspacing="0" border="0" style="background-color: #f9fafb; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="font-size: 13px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">Total Fare Paid:</td>
            <td style="font-size: 17px; color: #F86B17; text-align: right; font-weight: 800; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                KES {{ number_format($busBooking->total_price, 2) }}
            </td>
        </tr>
        @if(!empty($busBooking->mpesa_receipt_number))
        <tr>
            <td style="font-size: 12px; color: #9ca3af; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">M-Pesa Receipt:</td>
            <td style="font-size: 12px; color: #111827; text-align: right; font-weight: 600; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                {{ $busBooking->mpesa_receipt_number }}
            </td>
        </tr>
        @endif
    </table>

    <!-- Information note -->
    <p style="margin: 0; font-size: 13px; color: #6b7280; line-height: 1.6; text-align: center;">
        Show this email or your booking in the <strong>{{ config('app.name', 'ChapPlus') }}</strong> app to the conductor before boarding. Safe travels!
    </p>
@endsection
