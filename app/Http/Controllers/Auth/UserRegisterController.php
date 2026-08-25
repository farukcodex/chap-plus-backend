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
                    'name'       => $validated['name'],
                    'email'      => $validated['email'],
                    'password'   => $validated['password'],
                ]);

                $user->assignRole('user');

                $otpResult = $otpService->sendEmailOtp($user, 'register');

                if (! $otpResult['success']) {
                    throw new RuntimeException('OTP_SEND_FAILED');
                }

                return $user;
            });
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
