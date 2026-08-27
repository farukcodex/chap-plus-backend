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

    public function setup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'gender' => 'required|string|in:Male,Female,Other',
            'dob' => 'required|date',
            'address' => 'required|string|max:255'
        ]);

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
