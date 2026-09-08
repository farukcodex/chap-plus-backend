@extends('emails.layouts.app')

@section('title', config('app.name', 'ChapPlus') . ' — Verification Code')
@section('accent_color', '#557116')

@section('content')
    <!-- Security Badge -->
    <div style="margin-bottom: 12px;">
        @include('emails.partials.badge', [
            'type' => 'success',
            'icon' => '🔒',
            'text' => 'Verification Code'
        ])
    </div>

    <!-- Heading & Greeting -->
    <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.3px;">
        Verify Your Account
    </h1>
    <p style="margin: 0 0 6px; font-size: 15px; color: #111827;">
        Hi <strong>{{ $name }}</strong>,
    </p>
    <p style="margin: 0 0 28px; font-size: 14px; color: #4b5563; line-height: 1.6;">
        You requested a verification code for <strong>{{ $otp_from }}</strong>. Enter the code below to proceed:
    </p>

    <!-- OTP Code Display Card -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 12px;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #f4f6ec; border: 2px solid #557116; border-radius: 12px; padding: 18px 44px;">
                            <span style="font-size: 34px; font-weight: 800; letter-spacing: 10px; color: #3a4e0f; font-family: 'Courier New', Courier, monospace; display: block; padding-left: 10px;">
                                {{ $otp }}
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Expiry indicator -->
    <p style="margin: 0 0 28px; text-align: center; font-size: 12px; color: #9ca3af; font-family: 'Plus Jakarta Sans', Arial, sans-serif;">
        ⏱ This verification code expires in <strong style="color: #4b5563;">10 minutes</strong>.
    </p>

    <!-- Security Warning Box -->
    @include('emails.partials.alert-box', [
        'type' => 'warning',
        'title' => '⚠ Security Reminder',
        'message' => 'Never share this code with anyone. ' . config('app.name', 'ChapPlus') . ' will never call or message you asking for your verification code.'
    ])

    <!-- Sign-off -->
    <p style="margin: 0; font-size: 13px; color: #6b7280; line-height: 1.6;">
        If you did not request this code, you can safely disregard this email.<br><br>
        Warm regards,<br>
        <strong style="color: #111827;">The {{ config('app.name', 'ChapPlus') }} Team</strong>
    </p>
@endsection
