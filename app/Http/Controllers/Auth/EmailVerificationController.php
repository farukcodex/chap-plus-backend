<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponseTrait;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmMailRequest;
use App\Http\Requests\Auth\OtpResentRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Manage email verification and verification-code resend requests.
 */
class EmailVerificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Verify the email address using the submitted OTP code.
     */
    public function verify(ConfirmMailRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->apiError('Invalid email or otp', 404, ['code' => 'INVALID_EMAIL']);
        }

        if ($user->google_id) {
            return $this->apiError('User created using google. Please sign in using google.', 403, ['code' => 'USER_CREATED_USING_GOOGLE']);
        }

        if ($user->email_verified_at) {
            return $this->apiError('The mail is already verified', 409, ['code' => 'EMAIL_ALREADY_VERIFIED']);
        }

        if (! $this->hasValidOtp($user, $request->otp)) {
            return $this->apiError('Invalid or expired OTP, Please request a new one.', 410, ['code' => 'INVALID_OTP']);
        }

        // Clear the one-time code once it has been consumed successfully.
        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'email_verified_at' => Carbon::now(),
        ]);

        return $this->apiSuccess('Email successfully verified, now you can log in', $user->only(['id', 'name', 'email', 'email_verified_at']));
    }

    /**
     * Resend an email verification OTP when the previous code has expired.
     */
    public function resend(OtpResentRequest $request, OtpService $otpService): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->apiError('Invalid credentials.', 401, ['code' => 'INVALID_CREDENTIALS']);
        }

        // if ($user->email_verified_at) {
        //     return $this->apiError('This email address has already been verified.', 409, ['code' => 'EMAIL_ALREADY_VERIFIED']);
        // }

        // if ($user->status !== 'active') {
        //     return $this->apiError('Your account is not active.', 403, ['code' => 'ACCOUNT_NOT_ACTIVE']);
        // }

        // Attempt to send a new OTP (OtpService enforces the 1-minute cooldown)
        $otpResult = $otpService->sendEmailOtp($user, 'register');

        if (! $otpResult['success']) {
            return $this->apiError($otpResult['message'], 429, ['code' => 'OTP_SEND_FAILED']);
        }

        return $this->apiSuccess('A verification code has been sent to your email.', $user->only(['email']), 200);
    }

    /**
     * Check whether the submitted OTP matches the stored hash and is unexpired.
     */
    private function hasValidOtp(User $user, string $otp): bool
    {
        return $user->otp_code
            && $user->otp_expires_at
            && Hash::check($otp, $user->otp_code)
            && Carbon::now()->lte($user->otp_expires_at);
    }
}
