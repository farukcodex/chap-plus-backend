<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\RiderProfile;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

use App\Services\OtpService;
use RuntimeException;

class RiderRegisterController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'country' => 'required|string|size:2|alpha:ascii', 
            'city' => 'required|string|max:255',
        ]);

        try {
            $user = DB::transaction(function () use ($validated, $otpService): User {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);

                // Assign Spatie Role
                $user->assignRole('RIDER');

                // Auto-detect Currency from Country code
                try {
                    $isoData = (new \League\ISO3166\ISO3166)->alpha2($validated['country']);
                    $currency = isset($isoData['currency'][0]) ? $isoData['currency'][0] : null;
                } catch (\League\ISO3166\Exception\OutOfBoundsException $e) {
                    throw new \InvalidArgumentException('INVALID_COUNTRY_CODE');
                }

                // Create an empty profile to be filled out in the next steps, but pre-fill location
                RiderProfile::create([
                    'user_id' => $user->id,
                    'country' => $validated['country'],
                    'city' => $validated['city'],
                    'currency' => $currency,
                ]);

                // Send the OTP Email
                $otpResult = $otpService->sendEmailOtp($user, 'register');

                if (!$otpResult['success']) {
                    throw new RuntimeException('OTP_SEND_FAILED');
                }

                return $user;
            });
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'INVALID_COUNTRY_CODE') {
                return response()->json([
                    'message' => 'The provided country code is not a valid ISO 3166-1 alpha-2 code.',
                    'errors' => ['country' => ['Invalid country code.']]
                ], 422);
            }
            return $this->apiError('An error occurred during registration.', 500, ['error' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return $this->apiError('Unable to send verification code. Please try again later.', 500, [
                'code' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            return $this->apiError('Registration failed: ' . $e->getMessage(), 500);
        }

        return $this->apiSuccess('Registration successful. A verification code has been sent to your email.', [
            'user' => $user->only(['id', 'name', 'email']),
        ], 201);
    }
}
