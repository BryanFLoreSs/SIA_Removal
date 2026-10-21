<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WeatherController;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/mfa/challenge', [AuthController::class, 'verifyMfaChallenge'])->middleware('throttle:5,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/mfa/setup', [AuthController::class, 'setupMfa'])->middleware('throttle:5,1');
    Route::post('/mfa/enable', [AuthController::class, 'enableMfa'])->middleware('throttle:5,1');
    Route::get('/weather', [WeatherController::class, 'show'])->middleware('throttle:30,1');
    // TODO: Add TOTP enrollment/challenge routes with encrypted secrets and throttled verification.
});
