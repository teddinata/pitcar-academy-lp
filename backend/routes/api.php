<?php

use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DokuNotificationController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LearnController;
use App\Http\Controllers\Api\PurchaseFeedController;
use App\Http\Middleware\AuthenticateStudent;
use Illuminate\Support\Facades\Route;

Route::post('/leads', [LeadController::class, 'store'])
    ->middleware('throttle:leads')
    ->name('api.leads.store');

// Public social-proof feed for the sales popup. Masked data only; the
// throttle keeps scrapers from hammering it.
Route::get('/purchases/recent', [PurchaseFeedController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('api.purchases.recent');

Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.checkout.store');

Route::get('/checkout/courses/{slug}', [CheckoutController::class, 'course'])
    ->middleware('throttle:60,1')
    ->name('api.checkout.course');

Route::get('/checkout/orders/{number}', [CheckoutController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('api.checkout.show');

// Called by DOKU's servers, not by browsers. The signature is the gate.
Route::post('/payments/doku/notification', DokuNotificationController::class)
    ->name('api.doku.notification');

Route::post('/checkout/orders/{number}/access', [CheckoutController::class, 'access'])
    ->middleware('throttle:20,1')
    ->name('api.checkout.access');

Route::post('/checkout/orders/{number}/password', [CheckoutController::class, 'password'])
    ->middleware('throttle:20,1')
    ->name('api.checkout.password');

Route::prefix('learn')->group(function () {
    Route::post('/login', [LearnController::class, 'login'])->middleware('throttle:20,1');
    Route::post('/login-link', [LearnController::class, 'requestLink'])->middleware('throttle:5,1');
    Route::post('/session', [LearnController::class, 'startSession'])->middleware('throttle:20,1');

    Route::middleware(AuthenticateStudent::class)->group(function () {
        Route::delete('/session', [LearnController::class, 'endSession']);
        Route::post('/password', [LearnController::class, 'changePassword'])->middleware('throttle:10,1');
        Route::get('/courses', [LearnController::class, 'courses']);
        Route::get('/courses/{slug}', [LearnController::class, 'course']);
        Route::post('/lessons/{lesson}/progress', [LearnController::class, 'progress'])->middleware('throttle:120,1');
        Route::get('/attachments/{attachment}', [LearnController::class, 'download']);
    });
});
