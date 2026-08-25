<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponseTrait;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Class PasswordUpdateController
 *
 * This controller handles updating the password for an authenticated user.
 * It ensures the current password is valid before applying the new one.
 *
 * @package App\Http\Controllers\Auth
 */
class PasswordUpdateController extends Controller
{
    use ApiResponseTrait;

    /**
     * Update the authenticated user's password.
     *
     * @param PasswordUpdateRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function update(PasswordUpdateRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $request->validated();
        $currentPassword = $validated['current_password'];
        $newPassword = $validated['new_password'];

        if (! $this->isCurrentPasswordValid($currentPassword, $user->password)) {
            $msg = 'Current password is incorrect.';
            if ($user->google_id || $user->github_id) {
                $msg .= ' If you registered via Google, please use the Forgot Password link to set your password first.';
            }
            return $this->apiError($msg, 422, [
                'code' => 'CURRENT_PASSWORD_INCORRECT',
            ]);
        }

        if ($this->isSameAsCurrentPassword($newPassword, $user->password)) {
            return $this->apiError('New password must be different from old password', 422, [
                'code' => 'SAME_PASSWORD',
            ]);
        }

        $user->update([
            'password' => $newPassword,
        ]);

        // Revoke only the current token (if this request is authenticated via token).
        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return $this->apiSuccess(
            'Password changed successfully',
            $user->only(['id', 'name', 'email']),
            202
        );
    }

    private function isCurrentPasswordValid(string $currentPassword, string $hashedPassword): bool
    {
        return Hash::check($currentPassword, $hashedPassword);
    }

    private function isSameAsCurrentPassword(string $newPassword, string $hashedPassword): bool
    {
        return Hash::check($newPassword, $hashedPassword);
    }
}
