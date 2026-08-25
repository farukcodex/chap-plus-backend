<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * Return a standardized success JSON response.
     */
    protected function apiSuccess(string $message, mixed $data = null, int $code = 200)
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function apiError(string $message, int $code = 400, array $errors = [])
    {
        // Ensures we don't accidentally send a 200 as an error
        $status = ($code >= 400 && $code < 600) ? $code : 500;
        
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
