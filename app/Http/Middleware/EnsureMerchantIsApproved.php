<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantIsApproved
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

        if (!$user || !$user->merchantProfile || $user->merchantProfile->status !== 'approved') {
            return $this->apiError(
                'Your merchant account is pending approval. You cannot perform this action yet.', 
                403, 
                ['code' => 'MERCHANT_NOT_APPROVED']
            );
        }

        return $next($request);
    }
}
