@extends('emails.layouts.app')

@section('title', config('app.name', 'ChapPlus') . ' — Hotel Booking Voucher')
@section('accent_color', '#557116')

@section('content')
    <!-- Status Badge -->
    <div style="margin-bottom: 12px;">
        @include('emails.partials.badge', [
            'type' => 'success',
            'icon' => '🏨',
            'text' => 'Hotel Booking Confirmed'
        ])
    </div>

    <!-- Heading & Greeting -->
    <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.3px;">
        Your Stay is Confirmed!
    </h1>
    <p style="margin: 0 0 24px; font-size: 14px; color: #4b5563; line-height: 1.6;">
        Hi <strong>{{ $user->name }}</strong>, your reservation at <strong>{{ $hotelName }}</strong> is confirmed. Present this voucher or your booking code at check-in.
    </p>

    <!-- Booking Highlights Box -->
    <table width="100%" cellpadding="14" cellspacing="0" border="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 24px;">
        <tr>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Voucher / Booking Code:<br>
                <strong style="font-size: 16px; color: #111827; letter-spacing: 0.5px;">#HTL-{{ str_pad($booking->id, 5, '0', STR_PAD_LEFT) }}</strong>
            </td>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Accommodations:<br>
                <strong style="font-size: 15px; color: #111827;">{{ $booking->rooms_booked }} Room(s)</strong>
            </td>
        </tr>
        <tr>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Check-in Date:<br>
                <strong style="font-size: 14px; color: #557116;">📅 {{ \Carbon\Carbon::parse($booking->check_in_date)->format('D, M d, Y') }}</strong>
            </td>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Check-out Date:<br>
                <strong style="font-size: 14px; color: #dc2626;">📅 {{ \Carbon\Carbon::parse($booking->check_out_date)->format('D, M d, Y') }}</strong>
            </td>
        </tr>
        @if(!empty($hotelAddress))
        <tr>
            <td colspan="2" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                Hotel Location:<br>
                <strong style="font-size: 14px; color: #111827;">📍 {{ $hotelAddress }}</strong>
            </td>
        </tr>
        @endif
    </table>

    <!-- Payment Summary -->
    <table width="100%" cellpadding="8" cellspacing="0" border="0" style="background-color: #f9fafb; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="font-size: 13px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">Total Amount Paid:</td>
            <td style="font-size: 17px; color: #F86B17; text-align: right; font-weight: 800; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                USD {{ number_format($booking->total_price, 2) }}
            </td>
        </tr>
        @if(!empty($booking->mpesa_receipt_number))
        <tr>
            <td style="font-size: 12px; color: #9ca3af; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">M-Pesa Receipt:</td>
            <td style="font-size: 12px; color: #111827; text-align: right; font-weight: 600; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                {{ $booking->mpesa_receipt_number }}
            </td>
        </tr>
        @endif
    </table>

    <p style="margin: 0; font-size: 13px; color: #6b7280; line-height: 1.6; text-align: center;">
        Have questions about your stay? Contact hotel reception directly or reach out to <strong>{{ config('app.name', 'ChapPlus') }}</strong> support.
    </p>
@endsection
