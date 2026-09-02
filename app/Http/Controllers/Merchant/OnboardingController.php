<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MerchantProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Traits\ApiResponseTrait;
use App\Services\OtpService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    use ApiResponseTrait;

    /**
     * Handle merchant registration.
     */
    public function register(Request $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'merchant_type' => 'required|string|in:ECOMMERCE_MERCHANT,RESTAURANT_MERCHANT,HOTEL_MERCHANT,BUS_MERCHANT',
            // Enforce a valid 2-letter ISO 3166-1 alpha-2 country code (e.g., 'US', 'BD', 'GB')
            'country' => 'required|string|size:2|alpha:ascii', 
            'city' => 'required|string|max:255',
        ]);

        try {
            $user = DB::transaction(function () use ($validated, $otpService): User {
                // 1. Create the user
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);

                // 2. Assign Spatie Role 
                $user->assignRole($validated['merchant_type']);

                // 2. Auto-detect Currency from Country code
                try {
                    $isoData = (new \League\ISO3166\ISO3166)->alpha2($validated['country']);
                    $currency = isset($isoData['currency'][0]) ? $isoData['currency'][0] : null;
                } catch (\League\ISO3166\Exception\OutOfBoundsException $e) {
                    // Throw a specific error if they pass a 2-letter code that doesn't exist (like 'ZZ')
                    throw new \InvalidArgumentException('INVALID_COUNTRY_CODE');
                }

                // 3. Create the skeleton MerchantProfile
                MerchantProfile::create([
                    'user_id' => $user->id,
                    'country' => $validated['country'],
                    'city' => $validated['city'],
                    'currency' => $currency,
                ]);

                // 4. Send the OTP Email
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
            return $this->apiError('An error occurred during registration.', 500, [
                'error' => $e->getMessage()
            ]);
        } catch (RuntimeException $e) {
            return $this->apiError('Unable to send verification code. Please try again later.', 500, [
                'code' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            return $this->apiError('An error occurred during registration.', 500, [
                'error' => $e->getMessage()
            ]);
        }

        return $this->apiSuccess('Registration successful. A verification code has been sent to your email.', [
            'user' => $user->only(['id', 'name', 'email']),
        ], 201);
    }

    /**
     * Get the authenticated merchant's profile data.
     */
    public function setupProfile(Request $request): JsonResponse
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'address' => 'required|string',
            'description' => 'required|string',
            'profile_image' => 'nullable|image|max:5120', // 5MB Max
            'cover_image' => 'nullable|image|max:5120',
        ]);

        $user = $request->user();
        $merchantProfile = $user->merchantProfile;

        if (!$merchantProfile) {
            return $this->apiError('Merchant profile not found.', 404);
        }

        // Handle File Uploads
        $profileImagePath = $merchantProfile->profile_image_path;
        $coverImagePath = $merchantProfile->cover_image_path;

        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('merchant_profiles', 'public');
        }

        if ($request->hasFile('cover_image')) {
            $coverImagePath = $request->file('cover_image')->store('merchant_covers', 'public');
        }

        // Update Profile
        $merchantProfile->update([
            'business_name' => $request->business_name,
            'address' => $request->address,
            'description' => $request->description,
            'profile_image_path' => $profileImagePath,
            'cover_image_path' => $coverImagePath,
        ]);

        return app(\App\Http\Controllers\ProfileController::class)->index($request);
    }
}
