<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderIsApproved
{
    use ApiResponseTrait;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->riderProfile || $user->riderProfile->status !== 'approved') {
            return $this->apiError(
                'Your rider account is pending approval. You cannot access deliveries yet.', 
                403, 
                ['code' => 'RIDER_NOT_APPROVED']
            );
        }

        return $next($request);
    }
}
