<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Hotel Booking Voucher</title>
    <style>
        body, html { margin: 0; padding: 0; background-color: #f5f5f5; font-family: Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family: Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:580px;">
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
                                    <td style="background:#00897b; height:6px; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>

                            <div style="padding: 36px 40px;">
                                <!-- Badge -->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td>
                                            <span style="display:inline-block; padding: 4px 12px; background-color:#e0f2f1; color:#00695c; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase;">
                                                🏨 Hotel Booking Confirmed
                                            </span>
                                            <h1 style="margin: 12px 0 6px; font-size: 22px; font-weight: 700; color: #212121;">
                                                Your Stay is Confirmed!
                                            </h1>
                                            <p style="margin: 0 0 24px; font-size: 14px; color: #616161; line-height: 1.5;">
                                                Hi <strong>{{ $user->name }}</strong>, your reservation at <strong>{{ $hotelName }}</strong> is confirmed. Present this voucher or your booking ID at check-in.
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Booking Highlights Box -->
                                <table width="100%" cellpadding="14" cellspacing="0" border="0" style="background:#f9fafb; border-radius:8px; margin-bottom: 24px;">
                                    <tr>
                                        <td width="50%" style="font-size: 13px; color: #757575;">
                                            Booking ID:<br>
                                            <strong style="font-size: 16px; color: #212121;">#HTL-{{ str_pad($booking->id, 5, '0', STR_PAD_LEFT) }}</strong>
                                        </td>
                                        <td width="50%" style="font-size: 13px; color: #757575;">
                                            Rooms Booked:<br>
                                            <strong style="font-size: 15px; color: #212121;">{{ $booking->rooms_booked }} Room(s)</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="font-size: 13px; color: #757575; border-top: 1px solid #eeeeee;">
                                            Check-in Date:<br>
                                            <strong style="font-size: 15px; color: #00695c;">📅 {{ \Carbon\Carbon::parse($booking->check_in_date)->format('D, M d, Y') }}</strong>
                                        </td>
                                        <td style="font-size: 13px; color: #757575; border-top: 1px solid #eeeeee;">
                                            Check-out Date:<br>
                                            <strong style="font-size: 15px; color: #c62828;">📅 {{ \Carbon\Carbon::parse($booking->check_out_date)->format('D, M d, Y') }}</strong>
                                        </td>
                                    </tr>
                                    @if(!empty($hotelAddress))
                                    <tr>
                                        <td colspan="2" style="font-size: 13px; color: #757575; border-top: 1px solid #eeeeee;">
                                            Hotel Address:<br>
                                            <strong style="font-size: 14px; color: #333333;">📍 {{ $hotelAddress }}</strong>
                                        </td>
                                    </tr>
                                    @endif
                                </table>

                                <!-- Payment Summary -->
                                <table width="100%" cellpadding="6" cellspacing="0" border="0" style="margin-bottom: 24px; border-top: 1px solid #f0f0f0; padding-top: 12px;">
                                    <tr>
                                        <td style="font-size: 14px; color: #616161;">Total Amount Paid</td>
                                        <td style="font-size: 16px; color: #00695c; text-align: right; font-weight: 800;">
                                            USD {{ number_format($booking->total_price, 2) }}
                                        </td>
                                    </tr>
                                    @if(!empty($booking->mpesa_receipt_number))
                                    <tr>
                                        <td style="font-size: 13px; color: #9e9e9e;">M-Pesa Receipt</td>
                                        <td style="font-size: 13px; color: #212121; text-align: right; font-weight: 600;">
                                            {{ $booking->mpesa_receipt_number }}
                                        </td>
                                    </tr>
                                    @endif
                                </table>

                                <p style="margin: 0; font-size: 13px; color: #757575; line-height: 1.5; text-align: center;">
                                    For inquiries or changes to your reservation, contact hotel reception or Chap Plus customer support.
                                </p>
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
