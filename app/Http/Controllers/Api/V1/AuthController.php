<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\LoginResendOtpRequest;
use App\Http\Requests\Api\V1\Auth\LoginVerifyOtpRequest;
use App\Http\Requests\Api\V1\Auth\LogoutRequest;
use App\Http\Requests\Api\V1\Auth\PasswordResetConfirmRequest;
use App\Http\Requests\Api\V1\Auth\PasswordResetInitiateRequest;
use App\Http\Requests\Api\V1\Auth\PasswordResetResendOtpRequest;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\RegisterResendOtpRequest;
use App\Http\Requests\Api\V1\Auth\RegisterVerifyOtpRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    private function notImplemented(string $action): JsonResponse
    {
        return response()->json([
            'message' => "Auth action '{$action}' is not implemented yet.",
        ], 501);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        return response()->json($this->authService->register($request->validated()), 201);
    }

    public function registerResendOtp(RegisterResendOtpRequest $request): JsonResponse
    {
        return response()->json($this->authService->registerResendOtp($request->validated()));
    }

    public function registerVerifyOtp(RegisterVerifyOtpRequest $request): JsonResponse
    {
        return response()->json($this->authService->registerVerifyOtp($request->validated()));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->authService->login($request->validated()));
    }

    public function loginResendOtp(LoginResendOtpRequest $request): JsonResponse
    {
        return response()->json($this->authService->loginResendOtp($request->validated()));
    }

    public function loginVerifyOtp(LoginVerifyOtpRequest $request): JsonResponse
    {
        return response()->json($this->authService->loginVerifyOtp($request->validated()));
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        return response()->json($this->authService->refresh($request->validated()));
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        return response()->json($this->authService->logout($request->validated()));
    }

    public function passwordResetInitiate(PasswordResetInitiateRequest $request): JsonResponse
    {
        return response()->json($this->authService->passwordResetInitiate($request->validated()));
    }

    public function passwordResetResendOtp(PasswordResetResendOtpRequest $request): JsonResponse
    {
        return response()->json($this->authService->passwordResetResendOtp($request->validated()));
    }

    public function passwordResetConfirm(PasswordResetConfirmRequest $request): JsonResponse
    {
        return response()->json($this->authService->passwordResetConfirm($request->validated()));
    }
}
