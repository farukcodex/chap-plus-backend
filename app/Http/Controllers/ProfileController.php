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
            'id', 'name', 'email', 'phone',
            'email_verified_at', 'google_id', 'profile_photo_url',
            'created_at', 'updated_at',
        ]);
        $userData['role'] = $user->getRoleNames()->first();

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

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if it exists and is a local file
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

        // Remove profile_photo from validated since we mapped it to profile_photo_path
        unset($validated['profile_photo']);

        $user->update($validated);
        $user->refresh();

        $userData = $user->only([
            'id', 'name', 'email', 'phone',
            'email_verified_at', 'google_id', 'profile_photo_url',
            'created_at', 'updated_at',
        ]);
        $userData['role'] = $user->getRoleNames()->first();

        return $this->apiSuccess('Profile updated successfully.', $userData);
    }
}
