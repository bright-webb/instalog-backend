<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PageViewController;
use App\Http\Controllers\Api\PaymentController;


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('/send', [PaymentController::class, 'verify']);
Route::post('verify-email', [AuthController::class, 'verifyEmail']);
Route::post('resend-verification', [AuthController::class, 'resendVerificationCode']);
Route::post('check-verification-status', [AuthController::class, 'checkVerificationStatus']);
Route::post('/auth/refresh', [AuthController::class, 'refreshToken']);
Route::post('/record-view/{productId}', [ProductController::class, 'recordProductView']);

Route::post('/forgot-password', [AuthController::class, 'resetPassword']);
Route::post('/password/update', [AuthController::class, 'updatePassword']);

Route::get('/store/{store}', [StoreController::class, 'show'])->name('store.show');
Route::get('/store/{store}/products', [ProductController::class, 'index'])->name('store.products.index');
Route::post('/store/{store}/products/{product}', [ProductController::class, 'show'])->name('store.products.show');
Route::prefix('stores/{store}')->group(function () {
    Route::post('/rating', [StoreController::class, 'rateStore']);
    Route::post('/has-rated', [StoreController::class, 'hasRated']);
    Route::get('/ratings', [StoreController::class, 'getRatings']);
    Route::post('/record-inquiry', [StoreController::class, 'recordInquiry']);
});

Route::post('/products/{product_id}/like', [ProductController::class, 'likeProduct']);
Route::post('/products/{product_id}/rate', [ProductController::class, 'rateProduct']);
Route::post('/product/check-rating/{product_id}', [ProductController::class, 'checkRating']);

Route::middleware(['throttle:100,1'])->group(function () {
    Route::post('/page-views', [PageViewController::class, 'store']);
    Route::post('/page-views/batch', [PageViewController::class, 'batchStore']);
});

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/get-payment-plan', [PaymentController::class, 'getPaymentPlan']);
    Route::post('/create-plan', [PaymentController::class, 'createPlan']);
    Route::post('/create-payment', [PaymentController::class, 'createTransferPayment']);
    Route::post('/auth/password/create', [AuthController::class, 'createPassword']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('stores/analytics', [StoreController::class, 'storeAnalytics']);
    Route::apiResource('store', StoreController::class)->except(['show']);
    Route::apiResource('store.products', ProductController::class)->except(['index', 'show']);
    Route::post('/store/{slug}/products/{product}/update', [Productcontroller::class, 'update']);
    Route::post('/store/{slug}/update/logo', [StoreController::class, 'updateLogo']);
    Route::post('/store/{slug}/update/cover', [StoreController::class, 'updateCover']);

    Route::get('/analytics/page-views', [PageViewController::class, 'analytics']);
    Route::get('/analytics/popular-pages', [PageViewController::class, 'popularPages']);

    Route::delete('/products/{product_id}/reviews/{review_id}', [ProductController::class, 'deleteReview']);

});
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
