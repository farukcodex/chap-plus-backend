<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(Request $request)
    {
        $frontendUrl = $request->query('frontend_url', $request->headers->get('referer', config('app.frontend_url', config('app.url'))));
        $state = base64_encode(json_encode(['frontend_url' => rtrim($frontendUrl, '/')]));

        return Socialite::driver('google')->stateless()->with(['state' => $state])->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        if ($state = $request->query('state')) {
            $decoded = json_decode(base64_decode($state), true);
            if (isset($decoded['frontend_url'])) {
                $frontendUrl = $decoded['frontend_url'];
            }
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Check if user exists by google_id or email
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                // Prevent admins from logging in via Google
                if ($user->hasRole(['admin', 'super-admin'])) {
                    return redirect()->away($frontendUrl . '/login?error=' . urlencode('Administrators cannot log in using Google. Please use your email and password.'));
                }

                // Update google_id if it was null (e.g. user previously signed up with email)
                $updates = [];
                if (!$user->google_id) {
                    $updates['google_id'] = $googleUser->getId();
                }
                if (!$user->profile_photo_path && $googleUser->getAvatar()) {
                    $updates['profile_photo_path'] = $googleUser->getAvatar();
                }
                
                if (!empty($updates)) {
                    $user->update($updates);
                }

                if ($user->is_blocked) {
                    return redirect()->away($frontendUrl . '/banned');
                }
            } else {
                // Create a new user
                $user = User::create([
                    'name' => $googleUser->getName() ?? 'User',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'profile_photo_path' => $googleUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)), // Random password for oauth users
                    'email_verified_at' => now(), // Assume Google emails are verified
                ]);
                $user->assignRole('user');
            }

            // Create Sanctum Token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect back to frontend with the token
            return redirect()->away($frontendUrl . '/auth/callback?token=' . $token);
        } catch (\Exception $e) {
            \Log::error('Google Login Error: ' . $e->getMessage());
            return redirect()->away($frontendUrl . '/login?error=' . urlencode($e->getMessage()));
        }
    }
}
