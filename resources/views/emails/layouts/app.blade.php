<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', config('app.name', 'ChapPlus'))</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body, html {
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        table {
            border-collapse: collapse;
        }
        img {
            border: 0;
            outline: none;
            text-decoration: none;
        }
        @media only screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }
            .card-body {
                padding: 24px 20px !important;
            }
            .stack-column {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f3f4f6; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table class="email-container" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 580px; width: 100%;">
                    
                    <!-- ============================================== -->
                    <!-- BRAND HEADER: APP NAME                         -->
                    <!-- ============================================== -->
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <span style="font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.4px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; display: inline-block;">
                                {{ config('app.name', 'ChapPlus') }}
                            </span>
                        </td>
                    </tr>

                    <!-- ============================================== -->
                    <!-- MAIN CARD CONTAINER                            -->
                    <!-- ============================================== -->
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04); overflow: hidden;">
                            
                            <!-- Top Color Accent Bar -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background-color: @yield('accent_color', '#557116'); height: 6px; font-size: 0; line-height: 0;">&nbsp;</td>
                                </tr>
                            </table>

                            <!-- Inner Content Body -->
                            <div class="card-body" style="padding: 36px 40px;">
                                @yield('content')
                            </div>

                        </td>
                    </tr>

                    <!-- ============================================== -->
                    <!-- STANDARDIZED FOOTER                            -->
                    <!-- ============================================== -->
                    <tr>
                        <td align="center" style="padding: 28px 16px 12px; font-size: 12px; color: #9ca3af; line-height: 1.6; text-align: center; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
                            <p style="margin: 0 0 6px;">
                                This is an automated notification from <strong>{{ config('app.name', 'ChapPlus') }}</strong>.
                            </p>
                            <p style="margin: 0;">
                                &copy; {{ date('Y') }} {{ config('app.name', 'ChapPlus') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
