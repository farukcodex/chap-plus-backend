<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Class ProfileController
 *
 * Handles viewing and updating the authenticated user's profile.
 *
 * @package App\Http\Controllers
 */
class ProfileController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get the authenticated user's profile.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $userData = $user->only([
            'id', 'name', 'email',
            'email_verified_at', 'google_id', 'profile_photo_url',
            'created_at', 'updated_at',
        ]);
        $userData['role'] = $user->getRoleNames()->first();
        
        $profile = $user->userProfile;
        if ($profile) {
            $userData['country'] = $profile->country;
            $userData['city'] = $profile->city;
            $userData['currency'] = $profile->currency;
            $userData['phone'] = $profile->phone_number;
            $userData['gender'] = $profile->gender;
            $userData['date_of_birth'] = $profile->date_of_birth ? $profile->date_of_birth->format('Y-m-d') : null;
            $userData['address'] = $profile->address; // Legacy address field
            $userData['latitude'] = $profile->latitude !== null ? (float) $profile->latitude : null;
            $userData['longitude'] = $profile->longitude !== null ? (float) $profile->longitude : null;
            $userData['lat'] = $profile->latitude !== null ? (float) $profile->latitude : null;
            $userData['lon'] = $profile->longitude !== null ? (float) $profile->longitude : null;
        }

        // Include all saved delivery addresses
        $userData['saved_addresses'] = \App\Models\UserAddress::where('user_id', $user->id)
            ->select('id', 'title', 'address_text', 'latitude', 'longitude')
            ->get();

        if ($user->userProfile) {
            $userData['user_profile'] = $user->userProfile;
        }

        if ($user->merchantProfile) {
            $userData['merchant_profile'] = $user->merchantProfile;
        }

        if ($user->hasRole('RIDER') && $user->riderProfile) {
            $userData['rider_profile'] = $user->riderProfile;
        }

        return $this->apiSuccess('Profile retrieved successfully.', $userData);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param ProfileUpdateRequest $request
     * @return JsonResponse
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $request->validated();

        // Handle profile photo upload (stays on User table for now)
        if ($request->hasFile('profile_photo')) {
            if (
                $user->profile_photo_path &&
                ! \Illuminate\Support\Str::startsWith($user->profile_photo_path, ['http://', 'https://'])
            ) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $validated['profile_photo_path'] = $request
                ->file('profile_photo')
                ->store('profile-photos', 'public');
        }

        unset($validated['profile_photo']);

        // Separate User fields and Profile fields
        $userFields = [];
        if (isset($validated['name'])) $userFields['name'] = $validated['name'];
        if (isset($validated['email'])) $userFields['email'] = $validated['email'];
        if (isset($validated['profile_photo_path'])) $userFields['profile_photo_path'] = $validated['profile_photo_path'];

        $profileFields = [];
        if (isset($validated['phone'])) $profileFields['phone_number'] = $validated['phone'];
        if (isset($validated['gender'])) $profileFields['gender'] = $validated['gender'];
        if (isset($validated['date_of_birth'])) $profileFields['date_of_birth'] = $validated['date_of_birth'];
        if (isset($validated['address'])) $profileFields['address'] = $validated['address'];

        // Handle latitude / lat
        if ($request->has('lat') || $request->has('latitude')) {
            $profileFields['latitude'] = $request->input('lat', $request->input('latitude'));
        }

        // Handle longitude / lon
        if ($request->has('lon') || $request->has('longitude')) {
            $profileFields['longitude'] = $request->input('lon', $request->input('longitude'));
        }

        // Handle city
        if ($request->has('city')) {
            $profileFields['city'] = $request->input('city');
        }

        // Handle country: can only be saved if not already saved in the database
        if ($request->filled('country')) {
            $newCountry = strtoupper($request->input('country'));
            $existingCountry = $user->userProfile?->country;

            if (!empty($existingCountry)) {
                // Country is already saved - disallow changing to a different country
                if (strtoupper($existingCountry) !== $newCountry) {
                    return $this->apiError('Country cannot be changed once set.', 422, [
                        'country' => ['Country has already been set and cannot be modified.']
                    ]);
                }
            } else {
                // First-time setting: validate ISO 3166-1 alpha-2 code and auto-detect currency
                try {
                    $isoData = (new \League\ISO3166\ISO3166)->alpha2($newCountry);
                    $profileFields['country'] = $newCountry;
                    if (isset($isoData['currency'][0])) {
                        $profileFields['currency'] = $isoData['currency'][0];
                    }
                } catch (\League\ISO3166\Exception\OutOfBoundsException $e) {
                    return $this->apiError('The provided country code is not a valid ISO 3166-1 alpha-2 code.', 422, [
                        'country' => ['Invalid country code.']
                    ]);
                }
            }
        }

        if (!empty($userFields)) {
            $user->update($userFields);
        }

        if (!empty($profileFields) && $user->userProfile) {
            $user->userProfile->update($profileFields);
        } elseif (!empty($profileFields)) {
            $user->userProfile()->create($profileFields);
        }

        // Keep merchantProfile in sync if merchant
        if ($user->merchantProfile) {
            $merchantUpdates = [];
            if ($request->has('lat') || $request->has('latitude')) {
                $merchantUpdates['latitude'] = $request->input('lat', $request->input('latitude'));
            }
            if ($request->has('lon') || $request->has('longitude')) {
                $merchantUpdates['longitude'] = $request->input('lon', $request->input('longitude'));
            }
            if ($request->has('city')) {
                $merchantUpdates['city'] = $request->input('city');
            }
            if (isset($profileFields['country']) && empty($user->merchantProfile->country)) {
                $merchantUpdates['country'] = $profileFields['country'];
                if (isset($profileFields['currency']) && empty($user->merchantProfile->currency)) {
                    $merchantUpdates['currency'] = $profileFields['currency'];
                }
            }
            if (!empty($merchantUpdates)) {
                $user->merchantProfile->update($merchantUpdates);
            }
        }

        // Keep riderProfile in sync if rider
        if ($user->riderProfile) {
            $riderUpdates = [];
            if ($request->has('lat') || $request->has('latitude')) {
                $riderUpdates['latitude'] = $request->input('lat', $request->input('latitude'));
            }
            if ($request->has('lon') || $request->has('longitude')) {
                $riderUpdates['longitude'] = $request->input('lon', $request->input('longitude'));
            }
            if ($request->has('city')) {
                $riderUpdates['city'] = $request->input('city');
            }
            if (isset($profileFields['country']) && empty($user->riderProfile->country)) {
                $riderUpdates['country'] = $profileFields['country'];
                if (isset($profileFields['currency']) && empty($user->riderProfile->currency)) {
                    $riderUpdates['currency'] = $profileFields['currency'];
                }
            }
            if (!empty($riderUpdates)) {
                $user->riderProfile->update($riderUpdates);
            }
        }

        $user->refresh();

        return $this->index($request); // Reuse index to format response
    }

    /**
     * Delete the authenticated user's account.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Optional: Delete user's profile photo from storage before deleting the user
        if ($user->profile_photo_path && !\Illuminate\Support\Str::startsWith($user->profile_photo_path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
        
        // Let the DB cascading handle the MerchantProfile deletion if set up, 
        // but it's safe to just delete the user.
        $user->delete();

        return $this->apiSuccess('Account deleted successfully.');
    }
}
