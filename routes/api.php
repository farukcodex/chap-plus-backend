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
    Route::post('/rider/register', [\App\Http\Controllers\Auth\RiderRegisterController::class, 'store']);
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

    // Wallet (Unified for all authenticated users)
    Route::prefix('wallet')->group(function () {
        Route::get('/', [\App\Http\Controllers\WalletController::class, 'index']);
        Route::get('/transactions', [\App\Http\Controllers\WalletController::class, 'transactions']);
        Route::post('/payout', [\App\Http\Controllers\WalletController::class, 'requestPayout']);
        Route::get('/payouts', [\App\Http\Controllers\WalletController::class, 'payouts']);
    });

    // Customer E-commerce
    Route::prefix('ecommerce')->group(function () {
        Route::get('/home', [\App\Http\Controllers\Customer\EcommerceController::class, 'home']);
        Route::get('/categories', [\App\Http\Controllers\Customer\EcommerceController::class, 'categories']);
        Route::get('/products', [\App\Http\Controllers\Customer\EcommerceController::class, 'index']);
        Route::get('/products/{id}', [\App\Http\Controllers\Customer\EcommerceController::class, 'show']);
        Route::post('/products/{id}/reviews', [\App\Http\Controllers\Customer\EcommerceController::class, 'addReview']);
        
        Route::get('/stores', [\App\Http\Controllers\Customer\EcommerceController::class, 'stores']);
        Route::get('/stores/{id}', [\App\Http\Controllers\Customer\EcommerceController::class, 'storeDetails']);

        Route::prefix('favorites')->group(function () {
            Route::get('/', [\App\Http\Controllers\Customer\FavoriteController::class, 'index']);
            Route::post('/{id}', [\App\Http\Controllers\Customer\FavoriteController::class, 'toggle']);
        });

        Route::prefix('cart')->group(function () {
            Route::get('/', [\App\Http\Controllers\Customer\CartController::class, 'getCart']);
            Route::post('/add', [\App\Http\Controllers\Customer\CartController::class, 'addToCart']);
            Route::put('/items/{id}', [\App\Http\Controllers\Customer\CartController::class, 'updateCartItem']);
            Route::delete('/items/{id}', [\App\Http\Controllers\Customer\CartController::class, 'removeFromCart']);
        });

        Route::post('/checkout', [\App\Http\Controllers\Customer\CheckoutController::class, 'processCheckout']);
        Route::post('/orders/{id}/retry-payment', [\App\Http\Controllers\Customer\CheckoutController::class, 'retryPayment']);

        Route::prefix('orders')->group(function () {
            Route::get('/', [\App\Http\Controllers\Customer\OrderController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Customer\OrderController::class, 'show']);
            Route::post('/{id}/cancel', [\App\Http\Controllers\Customer\OrderController::class, 'cancel']);
            Route::post('/{id}/review', [\App\Http\Controllers\Customer\OrderController::class, 'review']);
            Route::get('/{id}/tracking', [\App\Http\Controllers\Customer\OrderController::class, 'tracking']);
        });

        Route::prefix('addresses')->group(function () {
            Route::get('/', [\App\Http\Controllers\Customer\UserAddressController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Customer\UserAddressController::class, 'store']);
            Route::delete('/{id}', [\App\Http\Controllers\Customer\UserAddressController::class, 'destroy']);
        });
    });
});

// M-Pesa Webhook (Must be outside auth middleware!)
Route::post('/webhooks/mpesa/callback', [\App\Http\Controllers\Customer\CheckoutController::class, 'mpesaWebhook']);

// Webhooks
Route::post('/checkout/callback', [\App\Http\Controllers\Customer\CheckoutController::class, 'handleCallback']);

// Public Pages
Route::get('/pages', [\App\Http\Controllers\Public\PageController::class, 'index']);
Route::get('/pages/{slug}', [\App\Http\Controllers\Public\PageController::class, 'show']);

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
        Route::post('/profile', [OnboardingController::class, 'setupProfile']);
        
        // E-commerce Merchant Routes
        Route::prefix('ecommerce')->middleware(['role:ECOMMERCE_MERCHANT'])->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Merchant\ProductController::class, 'getCategories']);
            Route::get('/products', [\App\Http\Controllers\Merchant\ProductController::class, 'index']);
            Route::post('/products', [\App\Http\Controllers\Merchant\ProductController::class, 'store']);
            Route::get('/products/{id}', [\App\Http\Controllers\Merchant\ProductController::class, 'show']);
            Route::put('/products/{id}', [\App\Http\Controllers\Merchant\ProductController::class, 'update']);
            Route::delete('/products/{id}', [\App\Http\Controllers\Merchant\ProductController::class, 'destroy']);
            Route::patch('/products/{id}/status', [\App\Http\Controllers\Merchant\ProductController::class, 'updateStatus']);

            // E-commerce Merchant Analytics Dashboard
            Route::get('/analytics', [\App\Http\Controllers\Merchant\AnalyticsController::class, 'index']);
            Route::get('/analytics/top-products', [\App\Http\Controllers\Merchant\AnalyticsController::class, 'topProducts']);

            // E-commerce Merchant Home Dashboard
            Route::get('/home', [\App\Http\Controllers\Merchant\HomeController::class, 'index']);
        });

        // Restaurant Merchant Routes
        Route::prefix('restaurant')->middleware(['role:RESTAURANT_MERCHANT'])->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Merchant\ProductController::class, 'getCategories']);
            Route::get('/foods', [\App\Http\Controllers\Merchant\ProductController::class, 'index']);
            Route::post('/foods', [\App\Http\Controllers\Merchant\ProductController::class, 'store']);
            Route::get('/foods/{id}', [\App\Http\Controllers\Merchant\ProductController::class, 'show']);
            Route::put('/foods/{id}', [\App\Http\Controllers\Merchant\ProductController::class, 'update']);
            Route::delete('/foods/{id}', [\App\Http\Controllers\Merchant\ProductController::class, 'destroy']);
            Route::patch('/foods/{id}/status', [\App\Http\Controllers\Merchant\ProductController::class, 'updateStatus']);

            // Restaurant Merchant Analytics Dashboard
            Route::get('/analytics', [\App\Http\Controllers\Merchant\AnalyticsController::class, 'index']);
            Route::get('/analytics/top-foods', [\App\Http\Controllers\Merchant\AnalyticsController::class, 'topProducts']);

            // Restaurant Merchant Home Dashboard
            Route::get('/home', [\App\Http\Controllers\Merchant\HomeController::class, 'index']);
        });

        // E-commerce Merchant Order Routes
        Route::prefix('orders')->group(function () {
            Route::get('/', [\App\Http\Controllers\Merchant\OrderController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Merchant\OrderController::class, 'show']);
            Route::patch('/{id}/status', [\App\Http\Controllers\Merchant\OrderController::class, 'updateStatus']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Admin Panel routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index']);
    Route::post('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'store']);

    Route::get('/pages/{slug}', [\App\Http\Controllers\Admin\PageController::class, 'show']);
    Route::put('/pages/{slug}', [\App\Http\Controllers\Admin\PageController::class, 'update']);

    Route::prefix('riders')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\RiderController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Admin\RiderController::class, 'show']);
        Route::patch('/{id}/status', [\App\Http\Controllers\Admin\RiderController::class, 'updateStatus']);
    });
});

/*
|--------------------------------------------------------------------------
| Rider routes
|--------------------------------------------------------------------------
*/
Route::prefix('rider')->middleware(['auth:sanctum', 'role:RIDER'])->group(function () {
    Route::post('/profile', [\App\Http\Controllers\Rider\ProfileController::class, 'updateProfile']);
    
    // Onboarding specific steps
    Route::post('/profile/setup', [\App\Http\Controllers\Rider\ProfileController::class, 'setup']);
    Route::post('/profile/documents', [\App\Http\Controllers\Rider\ProfileController::class, 'documents']);
    Route::post('/profile/finalize', [\App\Http\Controllers\Rider\ProfileController::class, 'finalize']);

    Route::prefix('deliveries')->middleware('rider.approved')->group(function () {
        Route::get('/', [\App\Http\Controllers\Rider\DeliveryController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Rider\DeliveryController::class, 'show']);
        Route::patch('/{id}/accept', [\App\Http\Controllers\Rider\DeliveryController::class, 'accept']);
        Route::patch('/{id}/pickup', [\App\Http\Controllers\Rider\DeliveryController::class, 'pickup']);
        Route::patch('/{id}/deliver', [\App\Http\Controllers\Rider\DeliveryController::class, 'deliver']);
        Route::post('/{id}/location', [\App\Http\Controllers\Rider\DeliveryController::class, 'updateLocation']);
    });
});