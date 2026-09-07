<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponseTrait;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handle logout requests for the authenticated user.
 */
class LogoutController extends Controller
{
    use ApiResponseTrait;

    /**
     * Revoke the current access token or all tokens for the authenticated user.
     *
     * Pass `type=all` to sign the user out from every active session.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Delete device push token if specified, or all tokens if full account logout
        if ($request->filled('expo_push_token')) {
            $user->deviceTokens()
                ->where('token', trim($request->expo_push_token))
                ->delete();
        } elseif ($this->shouldLogoutFromAllSessions($request)) {
            $user->deviceTokens()->delete();
        }

        // 2. Revoke Sanctum access token(s)
        if ($this->shouldLogoutFromAllSessions($request)) {
            // Revoke every personal access token for a full account logout.
            $user->tokens()->delete();

            return $this->apiSuccess('Logged out from all sessions.');
        }

        // Default to revoking only the token used for this request.
        $user->currentAccessToken()?->delete();

        return $this->apiSuccess('Logged out from current session.');
    }

    /**
     * Determine whether the request targets every active session.
     */
    private function shouldLogoutFromAllSessions(Request $request): bool
    {
        return $request->input('type') === 'all';
    }
}
