<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ config('app.name') }} — Verification Code</title>
    <style>
        body, html { margin: 0; padding: 0; background-color: #f5f5f5; }
        body { font-family: Roboto, 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5;">

    <!-- Outer wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">

                    <!-- App name header -->
                    <tr>
                        <td align="center" style="padding-bottom: 20px;">
                            <span style="font-size:17px; font-weight:700; color:#212121; letter-spacing:0.1px; font-family:Roboto,'Helvetica Neue',Arial,sans-serif;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>

                    <!-- Card -->
                    <tr>
                        <td style="background:#ffffff; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,.10), 0 4px 8px rgba(0,0,0,.07); overflow:hidden;">

                            <!-- Top accent bar -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:#1565c0; height:4px; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>

                            <!-- Card body -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding: 40px 48px;">

                                        <!-- Greeting -->
                                        <p style="margin:0 0 6px; font-size:15px; font-weight:400; color:#212121; font-family:Roboto,'Helvetica Neue',Arial,sans-serif;">
                                            Hi <strong>{{ $name }}</strong>,
                                        </p>

                                        <!-- Context -->
                                        <p style="margin:0 0 32px; font-size:14px; color:#616161; line-height:1.65; font-family:Roboto,'Helvetica Neue',Arial,sans-serif;">
                                            You requested a verification code for
                                            <strong style="color:#424242;">{{ $otp_from }}</strong>.
                                            Use the code below to proceed.
                                        </p>

                                        <!-- OTP label -->
                                        <p style="margin:0 0 10px; font-size:11px; font-weight:500; letter-spacing:1.2px; text-transform:uppercase; color:#9e9e9e; font-family:Roboto,'Helvetica Neue',Arial,sans-serif;">
                                            Your verification code
                                        </p>

                                        <!-- OTP code box -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;">
                                            <tr>
                                                <td align="center">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="background:#e3f2fd; border:2px solid #1565c0; border-radius:8px; padding:18px 48px; text-align:center;">
                                                                <span style="font-size:36px; font-weight:700; letter-spacing:12px; color:#0d47a1; font-family:'Courier New',Courier,monospace;">
                                                                    {{ $otp }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Expiry note -->
                                        <p style="margin:0 0 32px; text-align:center; font-size:12px; color:#9e9e9e; font-family:Roboto,'Helvetica Neue',Arial,sans-serif;">
                                            ⏱ This code expires in <strong style="color:#757575;">10 minutes</strong>.
                                        </p>

                                        <!-- Divider -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                                            <tr>
                                                <td style="border-top:1px solid #e0e0e0; font-size:0; line-height:0;">&nbsp;</td>
                                            </tr>
                                        </table>

                                        <!-- Security warning chip -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                                            <tr>
                                                <td style="background:#fff8e1; border-left:4px solid #ffc107; border-radius:0 4px 4px 0; padding:12px 16px;">
                                                    <p style="margin:0; font-size:13px; color:#795548; font-family:Roboto,'Helvetica Neue',Arial,sans-serif; line-height:1.5;">
                                                        <strong style="color:#5d4037;">⚠ Security reminder:</strong>
                                                        Never share this code with anyone.
                                                        {{ config('app.name') }} will never ask for your code.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Sign-off -->
                                        <p style="margin:0; font-size:14px; color:#616161; line-height:1.7; font-family:Roboto,'Helvetica Neue',Arial,sans-serif;">
                                            If you did not request this code, you can safely ignore this email.<br><br>
                                            Thanks,<br>
                                            <strong style="color:#424242;">{{ config('app.name') }} Team</strong>
                                        </p>

                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding-top: 24px;">
                            <p style="margin:0; font-size:12px; color:#9e9e9e; font-family:Roboto,'Helvetica Neue',Arial,sans-serif;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
