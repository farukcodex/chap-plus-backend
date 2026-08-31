<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'rider.approved' => \App\Http\Middleware\EnsureRiderIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Global exception renderer:
        // Converts framework/runtime exceptions into a consistent JSON shape.
        $exceptions->render(function (Throwable $e, Request $request) {
            // Default fallback for unhandled exceptions.
            $status = 500;
            $message = 'Backend server error.';
            $code = [
                'code' => "BACKEND_SERVER_ERROR"
            ];
            $errors = [
                'details' => $e->getMessage() // Includes the actual exception message
            ];

            // Input validation failures (e.g. missing/invalid form fields).
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $status = 422;
                $message = 'Something went wrong.';
                $code = ['code' => 'VALIDATION_ERROR'];
                $errors = $e->errors();
            }

            // Entity lookup failed (e.g. User::findOrFail($id)).
            elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $status = 404;
                $message = 'Resource not found.';
                $code = ['code' => 'RESOURCE_NOT_FOUND'];
            }

            // Route or file not found (e.g. abort(404)).
            elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $status = 404;
                $message = 'Endpoint or resource not found.';
                $code = ['code' => 'NOT_FOUND'];
            }

            // General HTTP Exceptions (like 429 Too Many Requests, 403 Forbidden)
            elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: 'HTTP Error ' . $status;
                $code = ['code' => 'HTTP_ERROR_' . $status];
            }

            // Missing/invalid auth token or unauthenticated access.
            elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $status = 401;
                $message = 'Unauthenticated.';
                $code = ['code' => 'UNAUTHENTICATED'];
            }

            // In debug mode, return raw exception details to speed up local dev.
            // Avoid enabling APP_DEBUG in production.
            if (config('app.debug')) {
                $message = $e->getMessage();
                $errors = [
                    'trace' => $e->getTraceAsString(),
                ];
            }

            // Unified API error format consumed by clients.
            $response = response()->json([
                'status'  => 'error',
                'message' => $message,
                'errors'  => array_merge($code, $errors),
            ], $status);

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $response->withHeaders($e->getHeaders());
            }

            return $response;
        });
    })->create();
