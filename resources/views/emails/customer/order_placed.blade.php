<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Order Confirmation</title>
    <style>
        body, html { margin: 0; padding: 0; background-color: #f5f5f5; font-family: Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family: Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
                    <!-- App Header -->
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <span style="font-size:22px; font-weight:800; color:#1565c0; letter-spacing:0.5px;">
                                {{ config('app.name', 'CHAP PLUS') }}
                            </span>
                        </td>
                    </tr>

                    <!-- Main Card -->
                    <tr>
                        <td style="background:#ffffff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.08); overflow:hidden;">
                            <!-- Top accent bar -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:#1565c0; height:6px; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>

                            <div style="padding: 36px 40px;">
                                <!-- Success Badge -->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td>
                                            <span style="display:inline-block; padding: 4px 12px; background-color:#e8f5e9; color:#2e7d32; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                                                ✓ Payment Confirmed
                                            </span>
                                            <h1 style="margin: 12px 0 6px; font-size: 22px; font-weight: 700; color: #212121;">
                                                Thank you for your order!
                                            </h1>
                                            <p style="margin: 0 0 24px; font-size: 14px; color: #616161; line-height: 1.5;">
                                                Hi <strong>{{ $user->name }}</strong>, we've received your payment and your order has been forwarded to <strong>{{ $merchantName }}</strong>.
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Order Info Box -->
                                <table width="100%" cellpadding="12" cellspacing="0" border="0" style="background:#f9fafb; border-radius:8px; margin-bottom: 24px;">
                                    <tr>
                                        <td width="50%" style="font-size: 13px; color: #757575;">
                                            Order Number:<br>
                                            <strong style="font-size: 15px; color: #212121;">{{ $orderNumber }}</strong>
                                        </td>
                                        <td width="50%" style="font-size: 13px; color: #757575;">
                                            Order Date:<br>
                                            <strong style="font-size: 14px; color: #212121;">{{ $order->created_at->format('M d, Y h:i A') }}</strong>
                                        </td>
                                    </tr>
                                    @if(!empty($order->mpesa_receipt_number))
                                    <tr>
                                        <td colspan="2" style="font-size: 13px; color: #757575; border-top: 1px solid #eeeeee;">
                                            M-Pesa Receipt:<br>
                                            <strong style="font-size: 14px; color: #2e7d32;">{{ $order->mpesa_receipt_number }}</strong>
                                        </td>
                                    </tr>
                                    @endif
                                </table>

                                <!-- Order Items Table -->
                                <h3 style="margin: 0 0 12px; font-size: 15px; font-weight: 700; color: #212121; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Items Summary
                                </h3>
                                <table width="100%" cellpadding="8" cellspacing="0" border="0" style="border-collapse: collapse; margin-bottom: 24px;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #eeeeee; text-align: left; font-size: 12px; color: #9e9e9e; text-transform: uppercase;">
                                            <th style="padding-bottom: 8px;">Item</th>
                                            <th style="padding-bottom: 8px; text-align: center;">Qty</th>
                                            <th style="padding-bottom: 8px; text-align: right;">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($items as $item)
                                        <tr style="border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #333333;">
                                            <td style="padding: 10px 0;">
                                                <strong>{{ $item['name'] }}</strong>
                                                @if(!empty($item['variant']))
                                                    <br><span style="font-size: 12px; color: #757575;">{{ $item['variant'] }}</span>
                                                @endif
                                            </td>
                                            <td style="padding: 10px 0; text-align: center; color: #616161;">
                                                {{ $item['quantity'] }}
                                            </td>
                                            <td style="padding: 10px 0; text-align: right; font-weight: 600;">
                                                {{ $currency }} {{ number_format($item['price'] * $item['quantity'], 2) }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <!-- Pricing Summary -->
                                <table width="100%" cellpadding="4" cellspacing="0" border="0" style="margin-bottom: 28px;">
                                    <tr>
                                        <td style="font-size: 14px; color: #616161;">Subtotal</td>
                                        <td style="font-size: 14px; color: #212121; text-align: right; font-weight: 600;">
                                            {{ $currency }} {{ number_format($order->total_amount, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="font-size: 14px; color: #616161;">Delivery Fee</td>
                                        <td style="font-size: 14px; color: #212121; text-align: right; font-weight: 600;">
                                            {{ $currency }} {{ number_format($order->delivery_fee, 2) }}
                                        </td>
                                    </tr>
                                    <tr style="border-top: 2px solid #212121;">
                                        <td style="padding-top: 10px; font-size: 16px; font-weight: 700; color: #212121;">Total Paid</td>
                                        <td style="padding-top: 10px; font-size: 18px; font-weight: 800; color: #1565c0; text-align: right;">
                                            {{ $currency }} {{ number_format($order->total_amount + $order->delivery_fee, 2) }}
                                        </td>
                                    </tr>
                                </table>

                                <!-- Delivery Address Box -->
                                @if(!empty($deliveryAddress))
                                <div style="background: #fdfdfd; border: 1px dashed #cccccc; border-radius: 8px; padding: 14px 16px; margin-bottom: 24px;">
                                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #757575; margin-bottom: 4px;">
                                        📍 Delivery Address
                                    </div>
                                    <div style="font-size: 14px; color: #333333; line-height: 1.4;">
                                        {{ $deliveryAddress }}
                                    </div>
                                </div>
                                @endif

                                <p style="margin: 0; font-size: 13px; color: #757575; line-height: 1.5; text-align: center;">
                                    You will receive live updates on your app as the store prepares and delivers your items.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 24px 16px; font-size: 12px; color: #9e9e9e;">
                            &copy; {{ date('Y') }} {{ config('app.name', 'Chap Plus') }}. All rights reserved.<br>
                            Need help? Contact support via your Chap Plus app.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
