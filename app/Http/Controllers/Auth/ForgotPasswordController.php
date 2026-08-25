<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponseTrait;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Class ForgotPasswordController
 *
 * This controller handles the password recovery process, including:
 * 1. Sending an OTP (One-Time Password) to the user's email.
 * 2. Verifying the OTP and providing a temporary password reset token.
 * 3. Resetting the password using the provided token.
 *
 * @package App\Http\Controllers\Auth
 */
class ForgotPasswordController extends Controller
{
    use ApiResponseTrait;

    /**
     * Send a password reset OTP to the user's email.
     *
     * @param ForgotPasswordRequest $request
     * @param OtpService $otpService
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function store(ForgotPasswordRequest $request, OtpService $otpService)
    {

        try {
            // Retrieve the user by email
            $user = User::where('email', $request->email)->first();

            // If user doesn't exist, return success to prevent email enumeration
            if (! $user) {
                return $this->apiSuccess('If the email exists, a verification code has been sent.');
            }

            // Note: We now allow Google/GitHub authenticated accounts to reset their password
            // so they can log in with both methods (Unified Identity).

            // If OTP is expired or hasn't been generated, send a new one
            if (! $user->otp_expires_at || Carbon::now()->gt($user->otp_expires_at)) {
                $otpService->sendEmailOtp($user, 'password_reset');
                $data = [
                    "email" => $user->email
                ];

                return $this->apiSuccess('A verification code has been sent to your email.', $data);
            }

            // OTP is still valid, inform the user
            return $this->apiSuccess('A verification code has already been sent to your email', $user->only(['email']));
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Verify the password reset OTP and generate a temporary reset token.
     *
     * @param PasswordResetRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function verify(PasswordResetRequest $request)
    {
        try {

            // Retrieve the user and validate the OTP
            $user = User::where('email', $request->email)->first();

            // Validate user existence and OTP authenticity/expiration
            if (! $user || ! $user->otp_code || ! Hash::check($request->otp, $user->otp_code) || now()->gt($user->otp_expires_at)) {
                return $this->apiError('Invalid or expired OTP', 422, ['code' => 'INVALID_OTP']);
            }

            // Generate a secure plain-text token for password reset
            $plainToken = Str::random(64);

            // Store hashed token and set a short expiration (10 minutes)
            $user->update([
                'password_reset_token' => hash('sha256', $plainToken),
                'password_reset_expires_at' => now()->addMinutes(10),
                'otp_verified_at' => Carbon::now(),
                'otp_code' => null,
                'otp_expires_at' => null,
            ]);

            // Mark email as verified if not already
            if (! $user->email_verified_at) {
                $user->update([
                    'email_verified_at' => Carbon::now(),
                ]);
            }

            // Revoke all existing Sanctum tokens for security
            $user->tokens()->delete();

            // Prepare the response data
            $data['email'] = $user->email;
            $data['password_reset_token'] = $plainToken;

            return $this->apiSuccess('OTP Verified Successfully', $data);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Reset the user's password using the provided reset token.
     *
     * @param UpdatePasswordRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function reset(UpdatePasswordRequest $request)
    {
        try {
            // Retrieve the user with a valid, non-expired reset token
            $user = User::where('email', $request->email)
                ->where('password_reset_token', hash('sha256', $request->password_reset_token))
                ->where('password_reset_expires_at', '>', now())
                ->first();

            // Return error if token is invalid or expired
            if (! $user) {
                return $this->apiError('Invalid or expired reset token', 422, ['code' => 'INVALID_RESET_TOKEN']);
            }

            if (Hash::check($request->password, $user->password)) {
                return $this->apiError('New password must be different from old password', 422, ['code' => 'SAME_PASSWORD']);
            }

            // Update the password and clear reset session data
            $user->update([
                'password' => $request->password,
                'password_reset_token' => null,
                'password_reset_expires_at' => null,
                'otp_verified_at' => null,
            ]);

            // Revoke all tokens to ensure security after password change
            $user->tokens()->delete();

            return $this->apiSuccess('Password reset successful', $user->only(['email']));
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
