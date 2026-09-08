<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — {{ $title }}</title>
    <style>
        body, html { margin: 0; padding: 0; background-color: #f5f5f5; font-family: Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family: Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
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
                                    <td style="background:{{ $isDelivered ? '#2e7d32' : '#c62828' }}; height:6px; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>

                            <div style="padding: 36px 40px;">
                                <!-- Status Badge -->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td>
                                            @if($isDelivered)
                                            <span style="display:inline-block; padding: 4px 12px; background-color:#e8f5e9; color:#2e7d32; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase;">
                                                ✓ Delivered
                                            </span>
                                            @else
                                            <span style="display:inline-block; padding: 4px 12px; background-color:#ffebee; color:#c62828; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase;">
                                                ✕ Cancelled
                                            </span>
                                            @endif
                                            <h1 style="margin: 12px 0 8px; font-size: 22px; font-weight: 700; color: #212121;">
                                                {{ $title }}
                                            </h1>
                                            <p style="margin: 0 0 20px; font-size: 15px; color: #616161; line-height: 1.5;">
                                                Hi <strong>{{ $user->name }}</strong>, {{ $statusMessage }}
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Order Details Box -->
                                <table width="100%" cellpadding="12" cellspacing="0" border="0" style="background:#f9fafb; border-radius:8px; margin-bottom: 24px;">
                                    <tr>
                                        <td width="50%" style="font-size: 13px; color: #757575;">
                                            Order Number:<br>
                                            <strong style="font-size: 15px; color: #212121;">{{ $orderNumber }}</strong>
                                        </td>
                                        <td width="50%" style="font-size: 13px; color: #757575;">
                                            Store / Merchant:<br>
                                            <strong style="font-size: 14px; color: #212121;">{{ $merchantName }}</strong>
                                        </td>
                                    </tr>
                                    @if(!empty($riderName))
                                    <tr>
                                        <td colspan="2" style="font-size: 13px; color: #757575; border-top: 1px solid #eeeeee;">
                                            Delivery Partner:<br>
                                            <strong style="font-size: 14px; color: #212121;">🛵 {{ $riderName }}</strong>
                                        </td>
                                    </tr>
                                    @endif
                                    @if(!$isDelivered && !empty($cancellationReason))
                                    <tr>
                                        <td colspan="2" style="font-size: 13px; color: #c62828; border-top: 1px solid #eeeeee;">
                                            Reason:<br>
                                            <strong style="font-size: 14px;">{{ $cancellationReason }}</strong>
                                        </td>
                                    </tr>
                                    @endif
                                </table>

                                @if($isDelivered)
                                <div style="text-align: center; padding: 12px 0 8px;">
                                    <p style="font-size: 14px; color: #616161; margin-bottom: 16px;">
                                        How was your experience? Your feedback helps us serve you better!
                                    </p>
                                    <a href="{{ config('app.url') }}" style="display:inline-block; padding: 12px 28px; background-color: #1565c0; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 700;">
                                        Rate Store & Delivery
                                    </a>
                                </div>
                                @else
                                <div style="background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #795548; line-height: 1.4;">
                                    <strong>Refund Information:</strong> If your account was charged via M-Pesa, your refund will be processed back to your original payment method or wallet within 24 hours.
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 24px 16px; font-size: 12px; color: #9e9e9e;">
                            &copy; {{ date('Y') }} {{ config('app.name', 'Chap Plus') }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
