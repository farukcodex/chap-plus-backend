@extends('emails.layouts.app')

@section('title', config('app.name', 'ChapPlus') . ' — Payment Failed')
@section('accent_color', '#dc2626')

@section('content')
    <!-- Status Badge -->
    <div style="margin-bottom: 12px;">
        @include('emails.partials.badge', [
            'type' => 'danger',
            'icon' => '⚠️',
            'text' => 'Payment Not Completed'
        ])
    </div>

    <!-- Heading & Greeting -->
    <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.3px;">
        Payment Failed & Seats Released
    </h1>
    <p style="margin: 0 0 16px; font-size: 14px; color: #4b5563; line-height: 1.6;">
        Hi <strong>{{ $user->name }}</strong>, we were unable to complete your payment for bus booking <strong>#BUS-{{ str_pad($busBooking->id, 5, '0', STR_PAD_LEFT) }}</strong> on <strong>{{ $busName }}</strong>.
    </p>

    <!-- Reason Alert Box -->
    @include('emails.partials.alert-box', [
        'type' => 'danger',
        'title' => 'Reason reported by payment gateway:',
        'message' => $reason
    ])

    <!-- Booking Summary Box -->
    <table width="100%" cellpadding="14" cellspacing="0" border="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 24px;">
        <tr>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Booking Code:<br>
                <strong style="font-size: 15px; color: #111827;">#BUS-{{ str_pad($busBooking->id, 5, '0', STR_PAD_LEFT) }}</strong>
            </td>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Released Seat(s):<br>
                <strong style="font-size: 16px; color: #dc2626; font-weight: 700;">💺 {{ $seats }}</strong>
            </td>
        </tr>
        <tr>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Travel Date:<br>
                <strong style="font-size: 14px; color: #111827;">📅 {{ $travelDate }}</strong>
            </td>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Bus Operator:<br>
                <strong style="font-size: 14px; color: #111827;">{{ $merchantName }}</strong>
            </td>
        </tr>
        @if(!empty($route))
        <tr>
            <td colspan="2" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                Route:<br>
                <strong style="font-size: 14px; color: #111827;">🛣️ {{ $route }}</strong>
            </td>
        </tr>
        @endif
    </table>

    <!-- Clarification Note -->
    <div style="background-color: #f3f4f6; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;">
        <p style="margin: 0; font-size: 13px; color: #4b5563; line-height: 1.6;">
            ℹ️ <strong>What happened?</strong><br>
            Because payment was cancelled or not authorized within the reservation window, the held seats have been released back to the general pool for other passengers. <strong>No money was deducted from your account.</strong>
        </p>
    </div>

    <!-- Re-book Guidance -->
    <p style="margin: 0; font-size: 13px; color: #6b7280; line-height: 1.6; text-align: center;">
        Need to travel? You can open the <strong>{{ config('app.name', 'ChapPlus') }}</strong> app to select available seats and book again anytime.
    </p>
@endsection
