<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Send email OTP to user
     */
    public function sendEmailOtp(User $user, string $otp_from): array
    {
        try {
            // Prevent OTP spam (1 minute cooldown)
            // OTP is valid for 10 minutes. If it expires in more than 9 minutes, it was sent less than 1 min ago.
            if ($user->otp_expires_at && $user->otp_expires_at->gt(now()->addMinutes(9))) {
                return [
                    'success' => false,
                    'message' => 'Please wait 1 minute before requesting a new code.',
                ];
            }

            // Generate otp
            $otp = random_int(100000, 999999);

            // Save it the otp and its expiration to database
            $user->forceFill([
                'otp_code' => Hash::make($otp),
                'otp_expires_at' => now()->addMinutes(10),
            ])->save();

            // Send email
            $user->notify(new EmailOtpNotification($otp,$otp_from));

            // Return the success message
            return [
                'success' => true,
                'message' => 'A verification code has been sent to your email address.',
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to send email OTP', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);

            // Return the error message
            return [
                'success' => false,
                'message' => 'Unable to send the verification code at this time. | ' . $e->getMessage(),
            ];
        }
    }
}
