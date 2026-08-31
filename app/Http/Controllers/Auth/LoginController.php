<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponseTrait;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Support\SubscriptionEntitlements;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Handle user authentication requests.
 */
class LoginController extends Controller
{
    use ApiResponseTrait;

    /**
     * Authenticate a user and issue a Sanctum token.
     *
     * @param LoginRequest $request The validated login request.
     * @param OtpService $otpService Service used for OTP-related login checks.
     * @return JsonResponse
     */
    public function store(LoginRequest $request, OtpService $otpService)
    {
        //Get the user
        $user = User::where('email', $request->email)->first();

        // Generic error (prevents enumeration if user not found, and blocks admins from using this route)
        if (! $user || $user->hasRole('admin')) {
            return $this->apiError('Invalid login credentials.', 401, ['code' => 'INVALID_CREDENTIALS']);
        }

        // Incorrect password handling
        if (! Hash::check($request->password, $user->password)) {
            if ($user->google_id || $user->github_id) {
                $provider = $user->google_id ? 'Google' : 'GitHub';
                return $this->apiError("Invalid login credentials. Note: This account is linked to {$provider}. You can also log in via {$provider}.", 401, ['code' => 'INVALID_CREDENTIALS_OAUTH']);
            }
            return $this->apiError('Invalid login credentials.', 401, ['code' => 'INVALID_CREDENTIALS']);
        }

        // Check if email verified.
        if (! $user->email_verified_at) {

            // if (! $user->otp_expires_at || Carbon::now()->gt($user->otp_expires_at)) {
            //     $otpService->sendEmailOtp($user, 'register');
            //     return $this->apiError('error with mail sending', 403, ['code' => 'OTP_SEND_FAILED']);
            // }

            return $this->apiError('Your email address is not verified. Please verify your email.', 403, ['code' => 'EMAIL_NOT_VERIFIED']);
        }

        // if($user->status)

        // Account status check
        // if ($user->status !== 'active') {
        //     return $this->apiError('Your account is '. $user->status, 403, ['code' => 'ACCOUNT_NOT_ACTIVE']);
        // }

        // Prevent too much login sessions.
        $activeSessions = $user->tokens()->count();
        if ($activeSessions >= 50) {
            $user->tokens()->delete();
        }

        // Create usertoken. also save the device name
        $token = $user->createToken($request->device_name ?? 'web')->plainTextToken;

        $userData = $user->only(['id', 'name', 'email', 'email_verified_at', 'google_id', 'profile_photo_url']);
        $userData['role'] = $user->getRoleNames()->first();

        $data = [
            'token_type' => 'Bearer',
            'token' => $token,
            'user'  =>  $userData,
        ];

        // Return success message.
        return $this->apiSuccess('Login successful.', $data);
    }

    /**
     * Authenticate an admin user and issue a Sanctum token.
     *
     * @param LoginRequest $request The validated login request.
     * @return JsonResponse
     */
    public function adminStore(LoginRequest $request)
    {
        //Get the user
        $user = User::where('email', $request->email)->first();

        // Generic error if not found
        if (! $user) {
            return $this->apiError('Invalid admin credentials.', 401, ['code' => 'INVALID_CREDENTIALS']);
        }

        // Check if user is actually an admin
        if (! $user->hasRole('ADMIN')) {
            return $this->apiError('Invalid admin credentials.', 401, ['code' => 'INVALID_CREDENTIALS']);
        }

        // Incorrect password handling
        if (! Hash::check($request->password, $user->password)) {
            return $this->apiError('Invalid admin credentials.', 401, ['code' => 'INVALID_CREDENTIALS']);
        }

        // Create usertoken.
        $token = $user->createToken('admin-token')->plainTextToken;

        $userData = $user->only(['id', 'name', 'email']);
        $userData['profile_photo_url'] = $user->profile_photo_url;
        $userData['role'] = $user->getRoleNames()->first();

        $data = [
            'token_type' => 'Bearer',
            'token' => $token,
            'user'  =>  $userData,
        ];

        // Return success message.
        return $this->apiSuccess('Admin login successful.', $data);
    }
}
