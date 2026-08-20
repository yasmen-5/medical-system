<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('register/resend-otp', [AuthController::class, 'registerResendOtp']);
        Route::post('register/verify-otp', [AuthController::class, 'registerVerifyOtp']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('login/resend-otp', [AuthController::class, 'loginResendOtp']);
        Route::post('login/verify-otp', [AuthController::class, 'loginVerifyOtp']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('password-reset', [AuthController::class, 'passwordResetInitiate']);
        Route::post('password-reset/resend-otp', [AuthController::class, 'passwordResetResendOtp']);
        Route::post('password-reset/confirm', [AuthController::class, 'passwordResetConfirm']);
    });

    Route::prefix('clinical')->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Clinical routes
        |--------------------------------------------------------------------------
        |
        | Mirrors the NestJS `clinical` module: sessions, encounters,
        | medical identity, history, and clinical document access.
        |
        */
    });

    Route::prefix('diagnostic')->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Diagnostic routes
        |--------------------------------------------------------------------------
        |
        | Mirrors the NestJS `diagnostic` controller: lab and imaging
        | sessions plus result uploads.
        |
        */
    });
});
