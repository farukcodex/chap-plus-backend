<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponseTrait;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UserRegisterRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Handle user account registration.
 */
class UserRegisterController extends Controller
{
    use ApiResponseTrait;

    public function store(UserRegisterRequest $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated, $otpService): User {
                $user = User::create([
                    'name'       => explode('@', $validated['email'])[0], // Default name
                    'email'      => $validated['email'],
                    'password'   => $validated['password'],
                ]);

                $user->assignRole('USER');

                // Auto-detect Currency from Country code
                try {
                    $isoData = (new \League\ISO3166\ISO3166)->alpha2($validated['country']);
                    $currency = isset($isoData['currency'][0]) ? $isoData['currency'][0] : null;
                } catch (\League\ISO3166\Exception\OutOfBoundsException $e) {
                    throw new \InvalidArgumentException('INVALID_COUNTRY_CODE');
                }

                \App\Models\UserProfile::create([
                    'user_id' => $user->id,
                    'country' => $validated['country'],
                    'city' => $validated['city'],
                    'currency' => $currency,
                ]);

                $otpResult = $otpService->sendEmailOtp($user, 'register');

                if (! $otpResult['success']) {
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
        }

        return $this->apiSuccess('Registration successful. A verification code has been sent to your email.', [
            'user' => $user->only(['id', 'name', 'email']),
        ], 201);
    }
}
