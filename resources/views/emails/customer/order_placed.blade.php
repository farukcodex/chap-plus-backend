@extends('emails.layouts.app')

@section('title', config('app.name', 'ChapPlus') . ' — Order Confirmation')
@section('accent_color', '#557116')

@section('content')
    <!-- Status Badge -->
    <div style="margin-bottom: 12px;">
        @include('emails.partials.badge', [
            'type' => 'success',
            'icon' => '✓',
            'text' => 'Payment Confirmed'
        ])
    </div>

    <!-- Heading & Greeting -->
    <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.3px;">
        Thank you for your order!
    </h1>
    <p style="margin: 0 0 24px; font-size: 14px; color: #4b5563; line-height: 1.6;">
        Hi <strong>{{ $user->name }}</strong>, we've received your payment and your order has been sent to <strong>{{ $merchantName }}</strong> for preparation.
    </p>

    <!-- Order Info Box -->
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
        <tr>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Order Date:<br>
                <strong style="font-size: 13px; color: #111827;">{{ $order->created_at->format('M d, Y h:i A') }}</strong>
            </td>
            <td style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px; vertical-align: top;">
                Payment Status:<br>
                <strong style="font-size: 13px; color: #557116;">Paid via {{ strtoupper($order->payment_method ?? 'M-Pesa') }}</strong>
            </td>
        </tr>
        @if(!empty($order->mpesa_receipt_number))
        <tr>
            <td colspan="2" style="font-size: 12px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                M-Pesa Receipt:<br>
                <strong style="font-size: 13px; color: #111827;">{{ $order->mpesa_receipt_number }}</strong>
            </td>
        </tr>
        @endif
    </table>

    <!-- Order Items Section -->
    <h3 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.8px;">
        Items Summary
    </h3>
    <table width="100%" cellpadding="10" cellspacing="0" border="0" style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr style="border-bottom: 2px solid #e5e7eb; text-align: left; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;">
                <th style="padding-bottom: 8px;">Item</th>
                <th style="padding-bottom: 8px; text-align: center;">Qty</th>
                <th style="padding-bottom: 8px; text-align: right;">Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr style="border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #1f2937;">
                <td style="padding: 12px 0;">
                    <strong>{{ $item['name'] }}</strong>
                    @if(!empty($item['variant']))
                        <br><span style="font-size: 12px; color: #6b7280;">{{ $item['variant'] }}</span>
                    @endif
                </td>
                <td style="padding: 12px 0; text-align: center; color: #4b5563; font-weight: 600;">
                    {{ $item['quantity'] }}
                </td>
                <td style="padding: 12px 0; text-align: right; font-weight: 700; color: #111827;">
                    {{ $currency }} {{ number_format($item['price'] * $item['quantity'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pricing Summary -->
    <table width="100%" cellpadding="6" cellspacing="0" border="0" style="background-color: #f9fafb; border-radius: 8px; padding: 10px 14px; margin-bottom: 24px;">
        <tr>
            <td style="font-size: 13px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">Subtotal</td>
            <td style="font-size: 13px; color: #111827; text-align: right; font-weight: 600; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                {{ $currency }} {{ number_format($order->total_amount, 2) }}
            </td>
        </tr>
        <tr>
            <td style="font-size: 13px; color: #6b7280; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">Delivery Fee</td>
            <td style="font-size: 13px; color: #111827; text-align: right; font-weight: 600; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                {{ $currency }} {{ number_format($order->delivery_fee, 2) }}
            </td>
        </tr>
        <tr>
            <td style="border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 15px; font-weight: 800; color: #111827; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">Total Paid</td>
            <td style="border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 18px; font-weight: 800; color: #F86B17; text-align: right; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                {{ $currency }} {{ number_format($order->total_amount + $order->delivery_fee, 2) }}
            </td>
        </tr>
    </table>

    <!-- Delivery Address Box -->
    @if(!empty($deliveryAddress))
    <div style="background-color: #fcfdfa; border: 1.5px dashed #557116; border-radius: 12px; padding: 14px 16px; margin-bottom: 24px;">
        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #557116; margin-bottom: 4px; letter-spacing: 0.5px;">
            📍 Delivery Destination
        </div>
        <div style="font-size: 14px; color: #1f2937; line-height: 1.5;">
            {{ $deliveryAddress }}
        </div>
    </div>
    @endif

    <p style="margin: 0; font-size: 13px; color: #6b7280; line-height: 1.6; text-align: center;">
        Track your rider's live location in the <strong>{{ config('app.name', 'ChapPlus') }}</strong> app.
    </p>
@endsection
