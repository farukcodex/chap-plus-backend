<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $riderProfile = $user->riderProfile;

        // if (!$riderProfile) {
        //     return $this->apiError('Rider profile not found.', 404);
        // }

        // $profileData = $riderProfile->toArray();
        // $profileData['role'] = $user->getRoleNames()->first();
        // $profileData['name'] = $user->name;
        // $profileData['email'] = $user->email;
        // $profileData['profile_photo_url'] = $user->profile_photo_url;

        return $this->apiSuccess('Rider profile retrieved successfully.', $user);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'sometimes|string|max:20',
            'gender' => 'sometimes|string|in:Male,Female,Other',
            'dob' => 'sometimes|date',
            'address' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|size:2|alpha:ascii',
            'city' => 'sometimes|string|max:255',
            'mpesa_payout_number' => 'sometimes|string|max:20',
            'profile_picture' => 'nullable|image|max:2048'
        ]);

        $user = $request->user();
        $profile = $user->riderProfile;

        if (!$profile) {
            return $this->apiError('Rider profile not found.', 404);
        }

        $profileFields = [];
        if (isset($validated['phone_number'])) $profileFields['phone_number'] = $validated['phone_number'];
        if (isset($validated['gender'])) $profileFields['gender'] = $validated['gender'];
        if (isset($validated['dob'])) $profileFields['dob'] = $validated['dob'];
        if (isset($validated['address'])) $profileFields['address'] = $validated['address'];
        if (isset($validated['country'])) $profileFields['country'] = $validated['country'];
        if (isset($validated['city'])) $profileFields['city'] = $validated['city'];
        if (isset($validated['mpesa_payout_number'])) $profileFields['mpesa_payout_number'] = $validated['mpesa_payout_number'];
        
        // Auto-detect Currency from Country code
        if (isset($validated['country'])) {
            try {
                $isoData = (new \League\ISO3166\ISO3166)->alpha2($validated['country']);
                $profileFields['currency'] = isset($isoData['currency'][0]) ? $isoData['currency'][0] : null;
            } catch (\League\ISO3166\Exception\OutOfBoundsException $e) {
                // Ignore invalid or handled by validation
            }
        }

        if (!empty($profileFields)) {
            $profile->update($profileFields);
        }

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('avatars', 'public');
            $user->update(['profile_photo_path' => $path]);
        }

        return $this->getProfile($request);
    }

    public function setup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'gender' => 'required|string|in:Male,Female,Other',
            'dob' => 'required|date',
            'address' => 'required|string|max:255',
            'country' => 'required|string|size:2|alpha:ascii',
            'city' => 'required|string|max:255',
        ]);
        
        // Auto-detect Currency from Country code
        try {
            $isoData = (new \League\ISO3166\ISO3166)->alpha2($validated['country']);
            $validated['currency'] = isset($isoData['currency'][0]) ? $isoData['currency'][0] : null;
        } catch (\League\ISO3166\Exception\OutOfBoundsException $e) {
            return response()->json([
                'message' => 'The provided country code is not a valid ISO 3166-1 alpha-2 code.',
                'errors' => ['country' => ['Invalid country code.']]
            ], 422);
        }

        // Update the rest on the RiderProfile table
        $profile = $request->user()->riderProfile;
        $profile->update($validated);

        return $this->apiSuccess('Basic profile details updated', ['profile' => $profile]);
    }

    public function documents(Request $request): JsonResponse
    {
        $request->validate([
            'license_image' => 'required|image|max:5120',
            'national_id_image' => 'required|image|max:5120',
        ]);

        $profile = $request->user()->riderProfile;

        if ($request->hasFile('license_image')) {
            $path = $request->file('license_image')->store('rider_documents', 'public');
            $profile->update(['license_image_path' => $path]);
        }

        if ($request->hasFile('national_id_image')) {
            $path = $request->file('national_id_image')->store('rider_documents', 'public');
            $profile->update(['national_id_image_path' => $path]);
        }

        return $this->apiSuccess('Documents uploaded successfully', ['profile' => $profile]);
    }

    public function finalize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mpesa_payout_number' => 'required|string|max:20',
            'profile_picture' => 'nullable|image|max:2048'
        ]);

        $profile = $request->user()->riderProfile;
        
        $profile->update([
            'mpesa_payout_number' => $validated['mpesa_payout_number'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('avatars', 'public');
            $request->user()->update(['profile_photo_path' => $path]);
        }

        return $this->apiSuccess('Rider registration finalized!', ['profile' => $profile]);
    }
}
