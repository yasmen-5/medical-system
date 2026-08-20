<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('login/resend-otp', [AuthController::class, 'loginResendOtp']);
        Route::post('login/verify-otp', [AuthController::class, 'loginVerifyOtp']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('password-reset', [AuthController::class, 'passwordResetInitiate']);
        Route::post('password-reset/resend-otp', [AuthController::class, 'passwordResetResendOtp']);
        Route::post('password-reset/confirm', [AuthController::class, 'passwordResetConfirm']);
    });

    Route::prefix('verification-queue')->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Verification queue routes
        |--------------------------------------------------------------------------
        |
        | Mirrors the NestJS admin verification flow for user approvals,
        | suspensions, and document downloads.
        |
        */
    });

    Route::prefix('users')->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | User management routes
        |--------------------------------------------------------------------------
        |
        | Mirrors the NestJS admin list/suspend/reactivate endpoints.
        |
        */
    });

    Route::prefix('stats')->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Admin statistics routes
        |--------------------------------------------------------------------------
        |
        | Mirrors the NestJS admin dashboard counters and activity views.
        |
        */
    });
});
