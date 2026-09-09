<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    use ApiResponseTrait;

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $frontendUrl = $request->query(
            'frontend_url',
            $request->headers->get('referer', config('app.frontend_url', config('app.url')))
        );
        $state = base64_encode(json_encode(['frontend_url' => rtrim($frontendUrl, '/')]));

        return Socialite::driver('google')->stateless()->with(['state' => $state])->redirect();
    }

    /**
     * Handle Google OAuth callback or direct token verification from mobile/API clients.
     * Google login is strictly restricted to user/customer accounts.
     */
    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        if ($state = $request->query('state')) {
            $decoded = json_decode(base64_decode($state), true);
            if (isset($decoded['frontend_url'])) {
                $frontendUrl = $decoded['frontend_url'];
            }
        }

        $wantsJson = $request->expectsJson() || $request->isJson() || $request->isMethod('POST');

        // Check if user cancelled or Google returned an error
        if ($request->has('error')) {
            $errorMsg = 'Google authentication was cancelled or failed: ' . $request->query('error');
            if ($wantsJson) {
                return $this->apiError($errorMsg, 400, ['code' => 'GOOGLE_AUTH_FAILED']);
            }
            return redirect()->away($frontendUrl . '/login?error=' . urlencode($errorMsg));
        }

        try {
            // Support both direct access token (mobile/SPA) and standard OAuth redirect flow
            if ($request->filled('access_token')) {
                $googleUser = Socialite::driver('google')->userFromToken($request->access_token);
            } elseif ($request->filled('id_token')) {
                $googleUser = $this->resolveUserFromIdToken($request->id_token);
            } else {
                $googleUser = Socialite::driver('google')->stateless()->user();
            }

            if (!$googleUser || !$googleUser->getEmail()) {
                throw new \RuntimeException('Failed to obtain user details from Google.');
            }

            // Check if user exists by google_id or email
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                // STRICT CHECK: Google login is ONLY permitted for customer/user accounts.
                // Disallow admins, riders, and all merchant types.
                if (!$user->hasRole('USER')) {
                    $errorMsg = 'Google login is only available for customer accounts. Merchants, riders, and administrators must log in using their email and password.';
                    if ($wantsJson) {
                        return $this->apiError($errorMsg, 403, ['code' => 'CUSTOMER_ONLY_GOOGLE_AUTH']);
                    }
                    return redirect()->away($frontendUrl . '/login?error=' . urlencode($errorMsg));
                }

                // Check if account is blocked
                if ($user->is_blocked) {
                    $errorMsg = 'Your account has been suspended by an administrator.';
                    if ($wantsJson) {
                        return $this->apiError($errorMsg, 403, ['code' => 'ACCOUNT_BLOCKED']);
                    }
                    return redirect()->away($frontendUrl . '/banned?error=' . urlencode($errorMsg));
                }

                // Link google_id or avatar if missing
                $updates = [];
                if (!$user->google_id) {
                    $updates['google_id'] = $googleUser->getId();
                }
                if (!$user->profile_photo_path && $googleUser->getAvatar()) {
                    $updates['profile_photo_path'] = $googleUser->getAvatar();
                }
                if (!$user->email_verified_at) {
                    $updates['email_verified_at'] = now();
                }

                if (!empty($updates)) {
                    $user->update($updates);
                }

                // Extract optional country/city provided by client or state, else null
                $country = $request->input('country') ?? ($decoded['country'] ?? null);
                $city = $request->input('city') ?? ($decoded['city'] ?? null);
                $currency = null;
                if ($country) {
                    $country = strtoupper($country);
                    try {
                        $isoData = (new \League\ISO3166\ISO3166)->alpha2($country);
                        $currency = isset($isoData['currency'][0]) ? $isoData['currency'][0] : null;
                    } catch (\Throwable $e) {
                        // Leave currency null if invalid country code
                    }
                }

                // Ensure UserProfile exists
                UserProfile::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'country'  => $country,
                        'city'     => $city,
                        'currency' => $currency,
                    ]
                );
            } else {
                // Extract optional country/city provided by client or state, else null
                $country = $request->input('country') ?? ($decoded['country'] ?? null);
                $city = $request->input('city') ?? ($decoded['city'] ?? null);
                $currency = null;
                if ($country) {
                    $country = strtoupper($country);
                    try {
                        $isoData = (new \League\ISO3166\ISO3166)->alpha2($country);
                        $currency = isset($isoData['currency'][0]) ? $isoData['currency'][0] : null;
                    } catch (\Throwable $e) {
                        // Leave currency null if invalid country code
                    }
                }

                // Create a new customer user (strictly role: 'USER')
                $user = DB::transaction(function () use ($googleUser, $country, $city, $currency): User {
                    $user = User::create([
                        'name'               => $googleUser->getName() ?: (explode('@', $googleUser->getEmail())[0] ?? 'User'),
                        'email'              => $googleUser->getEmail(),
                        'google_id'          => $googleUser->getId(),
                        'profile_photo_path' => $googleUser->getAvatar(),
                        'password'           => Hash::make(Str::random(32)),
                        'email_verified_at'  => now(),
                    ]);

                    $user->assignRole('USER');

                    UserProfile::create([
                        'user_id'  => $user->id,
                        'country'  => $country,
                        'city'     => $city,
                        'currency' => $currency,
                    ]);

                    return $user;
                });
            }

            // Create Sanctum Token
            $token = $user->createToken('auth_token')->plainTextToken;

            if ($wantsJson) {
                return $this->apiSuccess('Login successful.', [
                    'token_type' => 'Bearer',
                    'token'      => $token,
                    'user'       => [
                        'id'                => $user->id,
                        'name'              => $user->name,
                        'email'             => $user->email,
                        'role'              => 'USER',
                        'profile_photo_url' => $user->profile_photo_url,
                        'user_profile'      => $user->userProfile,
                    ],
                ]);
            }

            // Redirect back to frontend with the token
            return redirect()->away($frontendUrl . '/auth/callback?token=' . $token);
        } catch (\Exception $e) {
            Log::error('Google Login Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            if ($wantsJson) {
                return $this->apiError('Google authentication failed: ' . $e->getMessage(), 500, [
                    'code' => 'GOOGLE_AUTH_EXCEPTION',
                ]);
            }

            return redirect()->away($frontendUrl . '/login?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Resolve Google user from ID token payload.
     */
    private function resolveUserFromIdToken(string $idToken): \Laravel\Socialite\Two\User
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Invalid Google ID token.');
        }

        $payload = $response->json();

        $user = new \Laravel\Socialite\Two\User();
        $user->id = $payload['sub'] ?? null;
        $user->name = $payload['name'] ?? null;
        $user->email = $payload['email'] ?? null;
        $user->avatar = $payload['picture'] ?? null;

        return $user;
    }
}
