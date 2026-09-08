@extends('emails.layouts.app')

@section('title', config('app.name', 'ChapPlus') . ' — ' . $title)
@section('accent_color', $isDelivered ? '#557116' : '#dc2626')

@section('content')
    <!-- Status Badge -->
    <div style="margin-bottom: 12px;">
        @include('emails.partials.badge', [
            'type' => $isDelivered ? 'success' : 'danger',
            'icon' => $isDelivered ? '✓' : '✕',
            'text' => $isDelivered ? 'Delivered' : 'Cancelled'
        ])
    </div>

    <!-- Heading & Greeting -->
    <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.3px;">
        {{ $title }}
    </h1>
    <p style="margin: 0 0 20px; font-size: 14px; color: #4b5563; line-height: 1.6;">
        Hi <strong>{{ $user->name }}</strong>, {{ $statusMessage }}
    </p>

    <!-- Order Details Box -->
    <table width="100%" cellpadding="14" cellspacing="0" border="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 24px;">
        <tr>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Order Number:<br>
                <strong style="font-size: 15px; color: #111827;">{{ $orderNumber }}</strong>
            </td>
            <td width="50%" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; vertical-align: top;">
                Store / Merchant:<br>
                <strong style="font-size: 14px; color: #111827;">{{ $merchantName }}</strong>
            </td>
        </tr>
        @if(!empty($riderName))
        <tr>
            <td colspan="2" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                Delivery Partner:<br>
                <strong style="font-size: 14px; color: #111827;">🛵 {{ $riderName }}</strong>
            </td>
        </tr>
        @endif
        @if(!$isDelivered && !empty($cancellationReason))
        <tr>
            <td colspan="2" style="font-size: 12px; color: #dc2626; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #fee2e2; padding-top: 12px;">
                Cancellation Reason:<br>
                <strong style="font-size: 14px;">{{ $cancellationReason }}</strong>
            </td>
        </tr>
        @endif
    </table>

    @if($isDelivered)
        <div style="text-align: center; padding: 10px 0;">
            <p style="font-size: 14px; color: #4b5563; margin-bottom: 8px;">
                How was your experience? Your feedback helps our community!
            </p>
            @include('emails.partials.button', [
                'text' => 'Rate Store & Rider',
                'color' => 'primary',
                'url' => config('app.url')
            ])
        </div>
    @else
        @include('emails.partials.alert-box', [
            'type' => 'warning',
            'title' => 'Refund Information',
            'message' => 'If your account was debited, your refund is automatically returned to your original payment method or wallet within 24 hours.'
        ])
    @endif
@endsection
