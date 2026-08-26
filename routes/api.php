<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordUpdateController;
use App\Http\Controllers\Auth\UserRegisterController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Merchant\OnboardingController;
/*
|--------------------------------------------------------------------------
| Auth routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {

    Route::post('/register', [UserRegisterController::class, 'store']);
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/google/redirect', [GoogleController::class, 'redirect']);
    Route::get('/google/callback', [GoogleController::class, 'callback']);

    Route::post('/logout', [LogoutController::class, 'destroy'])->middleware(['auth:sanctum']);

    Route::post('/email/verify', [EmailVerificationController::class, 'verify']);
    Route::post('/email/resend', [EmailVerificationController::class, 'resend']);

    Route::post('/forgot-password', [ForgotPasswordController::class, 'store']);
    Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verify']);
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
});

/*
|--------------------------------------------------------------------------
| Shared Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // General
    Route::put('/profile/password', [PasswordUpdateController::class, 'update']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

    // Customer E-commerce
    Route::prefix('ecommerce')->group(function () {
        Route::get('/home', [\App\Http\Controllers\Customer\EcommerceController::class, 'home']);
        Route::get('/products', [\App\Http\Controllers\Customer\EcommerceController::class, 'index']);
        Route::get('/products/{id}', [\App\Http\Controllers\Customer\EcommerceController::class, 'show']);

        Route::prefix('cart')->group(function () {
            Route::get('/', [\App\Http\Controllers\Customer\CartController::class, 'getCart']);
            Route::post('/add', [\App\Http\Controllers\Customer\CartController::class, 'addToCart']);
            Route::put('/items/{id}', [\App\Http\Controllers\Customer\CartController::class, 'updateCartItem']);
            Route::delete('/items/{id}', [\App\Http\Controllers\Customer\CartController::class, 'removeFromCart']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Admin Auth routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth/admin')->group(function () {
    Route::post('/login', [LoginController::class, 'adminStore']);
});

/*
|--------------------------------------------------------------------------
| Merchant routes
|--------------------------------------------------------------------------
*/

Route::prefix('merchant')->group(function () {
    Route::post('/register', [OnboardingController::class, 'register']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [OnboardingController::class, 'getProfile']);
        Route::post('/profile', [OnboardingController::class, 'setupProfile']);
        
        // E-commerce Merchant Routes
        Route::prefix('ecommerce')->middleware(['role:ECOMMERCE_MERCHANT'])->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Merchant\ProductController::class, 'getCategories']);
            Route::get('/products', [\App\Http\Controllers\Merchant\ProductController::class, 'index']);
            Route::post('/products', [\App\Http\Controllers\Merchant\ProductController::class, 'store']);
            Route::get('/products/{id}', [\App\Http\Controllers\Merchant\ProductController::class, 'show']);
            Route::patch('/products/{id}/status', [\App\Http\Controllers\Merchant\ProductController::class, 'updateStatus']);
        });
    });
});