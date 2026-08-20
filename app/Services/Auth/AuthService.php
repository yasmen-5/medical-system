<?php

namespace App\Services\Auth;

use App\Models\LoginSession;
use App\Models\PasswordResetSession;
use App\Models\RefreshToken;
use App\Models\RegistrationSession;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class AuthService
{
    private const OTP_MINUTES = 5;
    private const REGISTRATION_SESSION_MINUTES = 30;
    private const LOGIN_SESSION_MINUTES = 10;
    private const PASSWORD_RESET_SESSION_MINUTES = 10;
    private const REFRESH_TOKEN_DAYS = 7;
    private const ACCESS_TOKEN_MINUTES = 60;

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendOtpEmail(string $email, string $subject, string $message): void
    {
        Mail::raw($message, function ($mail) use ($email, $subject): void {
            $mail->to($email)->subject($subject);
        });
    }

    private function issueAccessToken(User $user, int $minutes = self::ACCESS_TOKEN_MINUTES): string
    {
        $payload = [
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'exp' => now()->addMinutes($minutes)->timestamp,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $secret = (string) env('JWT_ACCESS_SECRET', config('app.key'));
        $signature = hash_hmac('sha256', $encoded, $secret);

        return $encoded.'.'.$signature;
    }

    private function generateRefreshToken(): array
    {
        return [
            'tokenId' => (string) Str::uuid(),
            'tokenValue' => Str::random(64),
        ];
    }

    private function parseRefreshToken(string $refreshToken): array
    {
        $parts = explode('.', $refreshToken);

        if (count($parts) !== 2 || ! Str::isUuid($parts[0])) {
            throw new RuntimeException('Invalid refresh token');
        }

        return [
            'tokenId' => $parts[0],
            'tokenValue' => $parts[1],
        ];
    }

    private function userToPayload(User $user): array
    {
        return [
            'userId' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    public function register(array $payload): array
    {
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');
        $role = (string) ($payload['role'] ?? '');

        if ($email === '' || $password === '' || $role === '') {
            throw new RuntimeException('Missing required registration fields.');
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser && $existingUser->account_status !== 'PENDING') {
            throw new RuntimeException('Email is already registered');
        }

        if (RegistrationSession::query()->where('email', $email)->exists()) {
            throw new RuntimeException(
                'A registration with this email already exists and is pending verification',
            );
        }

        $registrationSession = DB::transaction(function () use ($payload, $email, $password, $role): RegistrationSession {
            $registrationSession = RegistrationSession::query()->create([
                'email' => $email,
                'password_hash' => Hash::make($password),
                'role' => $role,
                'registration_data' => collect($payload)
                    ->except(['password'])
                    ->all(),
                'registration_documents' => [],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent() ?? 'unknown',
                'expires_at' => now()->addMinutes(self::REGISTRATION_SESSION_MINUTES),
            ]);

            $otp = $this->generateOtp();
            UserOtp::query()->create([
                'register_session_id' => $registrationSession->id,
                'otp_hash' => Hash::make($otp),
                'mfa_method' => 'EMAIL_OTP',
                'purpose' => 'Registration OTP',
                'expires_at' => now()->addMinutes(self::OTP_MINUTES),
            ]);

            $this->sendOtpEmail(
                $email,
                'Email Verification',
                "Your verification code is {$otp}. It expires in 5 minutes.",
            );

            return $registrationSession;
        });

        return [
            'registrationSessionId' => $registrationSession->id,
            'otpDelivery' => $email,
            'expiresAt' => now()->addMinutes(self::OTP_MINUTES)->toISOString(),
        ];
    }

    public function registerResendOtp(array $payload): array
    {
        $sessionId = (string) ($payload['registrationSessionId'] ?? '');
        $session = RegistrationSession::query()->find($sessionId);

        if (! $session) {
            throw new RuntimeException('Registration session not found');
        }

        if ($session->expires_at->isPast()) {
            throw new RuntimeException('Registration session has expired');
        }

        UserOtp::query()
            ->where('register_session_id', $session->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = $this->generateOtp();
        $otpRecord = UserOtp::query()->create([
            'register_session_id' => $session->id,
            'otp_hash' => Hash::make($otp),
            'mfa_method' => 'EMAIL_OTP',
            'purpose' => 'Registration OTP',
            'expires_at' => now()->addMinutes(self::OTP_MINUTES),
        ]);

        $this->sendOtpEmail(
            $session->email,
            'Email Verification',
            "Your verification code is {$otp}. It expires in 5 minutes.",
        );

        return [
            'registrationSessionId' => $session->id,
            'otpDelivery' => $session->email,
            'expiresAt' => $otpRecord->expires_at->toISOString(),
        ];
    }

    public function registerVerifyOtp(array $payload): array
    {
        $sessionId = (string) ($payload['registrationSessionId'] ?? '');
        $otp = (string) ($payload['otp'] ?? '');

        $session = RegistrationSession::query()->find($sessionId);
        if (! $session) {
            throw new RuntimeException('Registration session not found');
        }

        if ($session->expires_at->isPast()) {
            throw new RuntimeException('Registration session has expired');
        }

        $otpRecord = UserOtp::query()
            ->where('register_session_id', $session->id)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (! $otpRecord) {
            throw new RuntimeException('OTP not found or does not belong to this registration session');
        }

        if ($otpRecord->expires_at->isPast()) {
            throw new RuntimeException('OTP has expired');
        }

        if ($otpRecord->used_at !== null) {
            throw new RuntimeException('OTP has already been used');
        }

        if (! Hash::check($otp, $otpRecord->otp_hash)) {
            throw new RuntimeException('Invalid OTP');
        }

        return DB::transaction(function () use ($session, $otpRecord): array {
            $otpRecord->forceFill(['used_at' => now()])->save();

            $user = User::query()->create([
                'email' => $session->email,
                'phone_number' => data_get($session->registration_data, 'phoneNumber'),
                'password_hash' => $session->password_hash,
                'role' => $session->role,
                'account_status' => 'PENDING',
                'email_verified' => true,
                'mfa_method' => 'EMAIL_OTP',
                'mfa_secret' => null,
            ]);

            $registrationData = $session->registration_data;

            if ($session->role === 'PATIENT') {
                DB::table('patients')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'first_name' => (string) data_get($registrationData, 'firstName', ''),
                    'middle_name' => (string) data_get($registrationData, 'middleName', ''),
                    'surname' => (string) data_get($registrationData, 'surName', ''),
                    'gender' => (string) data_get($registrationData, 'gender', 'MALE'),
                    'date_of_birth' => data_get($registrationData, 'dateOfBirth'),
                    'national_id' => (string) data_get($registrationData, 'nationalId', ''),
                    'blood_type' => data_get($registrationData, 'bloodType'),
                    'weight_kg' => data_get($registrationData, 'weightKg'),
                    'height_cm' => data_get($registrationData, 'heightCm'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($session->role === 'HEALTHCARE_PROVIDER') {
                DB::table('healthcare_providers')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'first_name' => (string) data_get($registrationData, 'firstName', ''),
                    'middle_name' => (string) data_get($registrationData, 'middleName', ''),
                    'surname' => (string) data_get($registrationData, 'surName', ''),
                    'gender' => (string) data_get($registrationData, 'gender', 'MALE'),
                    'date_of_birth' => data_get($registrationData, 'dateOfBirth'),
                    'national_id' => (string) data_get($registrationData, 'nationalId', ''),
                    'medical_license_number' => (string) data_get($registrationData, 'medicalLicenseNumber', ''),
                    'specialization' => (string) data_get($registrationData, 'specialization', ''),
                    'workplace_name' => data_get($registrationData, 'workplaceName'),
                    'workplace_address' => data_get($registrationData, 'workplaceAddress'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($session->role === 'LAB') {
                DB::table('laboratories')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'lab_name' => (string) data_get($registrationData, 'centerName', ''),
                    'registration_number' => (string) data_get($registrationData, 'registrationNumber', ''),
                    'administrator_full_name' => (string) data_get($registrationData, 'administratorFullName', ''),
                    'lab_address' => data_get($registrationData, 'centerAddress'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($session->role === 'IMAGING_CENTER') {
                DB::table('imaging_centers')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'center_name' => (string) data_get($registrationData, 'centerName', ''),
                    'registration_number' => (string) data_get($registrationData, 'registrationNumber', ''),
                    'administrator_full_name' => (string) data_get($registrationData, 'administratorFullName', ''),
                    'center_address' => data_get($registrationData, 'centerAddress'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->sendOtpEmail(
                $session->email,
                'Welcome to Sijill - Application Under Review',
                'Your registration has been completed successfully and is under review.',
            );

            return [
                'success' => true,
                'message' => 'Registration completed successfully. Your application is under review.',
                'userId' => $user->id,
                'email' => $session->email,
                'role' => $session->role,
            ];
        });
    }

    public function login(array $payload): array
    {
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            throw new RuntimeException('Invalid email or password');
        }

        if (! Hash::check($password, $user->password_hash)) {
            throw new RuntimeException('Invalid email or password');
        }

        if ($user->account_status === 'PENDING') {
            throw new RuntimeException('Your account is pending approval. Please wait for admin verification.');
        }

        if ($user->account_status === 'REJECTED') {
            throw new RuntimeException('Your registration application has been rejected. Please review the email sent to you for further details.');
        }

        if ($user->account_status === 'SUSPENDED') {
            throw new RuntimeException('Your account has been suspended. Please contact support.');
        }

        if ($user->account_status === 'DEACTIVATED') {
            throw new RuntimeException('Your account has been deactivated. Please contact support.');
        }

        if (! $user->email_verified) {
            throw new RuntimeException('Please verify your email before logging in.');
        }

        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now()]);

        LoginSession::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->delete();

        $loginSession = LoginSession::query()->create([
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ?? 'unknown',
            'expires_at' => now()->addMinutes(self::LOGIN_SESSION_MINUTES),
        ]);

        $otp = $this->generateOtp();
        UserOtp::query()->create([
            'user_id' => $user->id,
            'login_session_id' => $loginSession->id,
            'otp_hash' => Hash::make($otp),
            'mfa_method' => 'EMAIL_OTP',
            'purpose' => 'Login OTP',
            'expires_at' => now()->addMinutes(self::OTP_MINUTES),
        ]);

        $this->sendOtpEmail(
            $user->email,
            'Login Verification',
            "Your verification code is {$otp}. It expires in 5 minutes.",
        );

        return [
            'loginSessionId' => $loginSession->id,
            'otpDelivery' => $user->email,
            'expiresAt' => now()->addMinutes(self::OTP_MINUTES)->toISOString(),
        ];
    }

    public function loginResendOtp(array $payload): array
    {
        $sessionId = (string) ($payload['loginSessionId'] ?? '');
        $session = LoginSession::query()->find($sessionId);

        if (! $session) {
            throw new RuntimeException('Login session not found or has expired');
        }

        if ($session->expires_at->isPast()) {
            throw new RuntimeException('Login session has expired. Please login again');
        }

        UserOtp::query()
            ->where('login_session_id', $session->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $user = User::query()->find($session->user_id);
        if (! $user) {
            throw new RuntimeException('Invalid authentication context.');
        }

        $otp = $this->generateOtp();
        $otpRecord = UserOtp::query()->create([
            'user_id' => $user->id,
            'login_session_id' => $session->id,
            'otp_hash' => Hash::make($otp),
            'mfa_method' => 'EMAIL_OTP',
            'purpose' => 'Login OTP',
            'expires_at' => now()->addMinutes(self::OTP_MINUTES),
        ]);

        $this->sendOtpEmail(
            $user->email,
            'Login Verification',
            "Your verification code is {$otp}. It expires in 5 minutes.",
        );

        return [
            'loginSessionId' => $session->id,
            'otpDelivery' => $user->email,
            'expiresAt' => $otpRecord->expires_at->toISOString(),
        ];
    }

    public function loginVerifyOtp(array $payload): array
    {
        $sessionId = (string) ($payload['loginSessionId'] ?? '');
        $otp = (string) ($payload['otp'] ?? '');

        $session = LoginSession::query()->find($sessionId);
        if (! $session) {
            throw new RuntimeException('Login session not found or has expired');
        }

        if ($session->expires_at->isPast()) {
            throw new RuntimeException('Login session has expired. Please login again');
        }

        $otpRecord = UserOtp::query()
            ->where('login_session_id', $session->id)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (! $otpRecord) {
            throw new RuntimeException('OTP not found or does not belong to this login session');
        }

        if ($otpRecord->expires_at->isPast()) {
            throw new RuntimeException('OTP has expired');
        }

        if ($otpRecord->used_at !== null) {
            throw new RuntimeException('OTP has already been used');
        }

        if (! Hash::check($otp, $otpRecord->otp_hash)) {
            throw new RuntimeException('Invalid OTP');
        }

        $user = User::query()->find($session->user_id);
        if (! $user) {
            throw new RuntimeException('User not found');
        }

        $otpRecord->forceFill(['used_at' => now()])->save();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        $accessToken = $this->issueAccessToken($user);
        $refresh = $this->generateRefreshToken();
        $expiresAt = now()->addDays(self::REFRESH_TOKEN_DAYS);

        RefreshToken::query()->create([
            'id' => $refresh['tokenId'],
            'user_id' => $user->id,
            'token_hash' => Hash::make($refresh['tokenValue']),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'issued_at' => now(),
            'issued_ip' => request()->ip(),
            'user_agent' => request()->userAgent() ?? 'unknown',
            'parent_token_id' => null,
        ]);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'userId' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'accessToken' => $accessToken,
            'refreshToken' => $refresh['tokenId'].'.'.$refresh['tokenValue'],
        ];
    }

    public function refresh(array $payload): array
    {
        $refreshToken = (string) ($payload['refreshToken'] ?? '');

        if ($refreshToken === '') {
            throw new RuntimeException('Refresh token not found');
        }

        $parsed = $this->parseRefreshToken($refreshToken);
        $token = RefreshToken::query()->find($parsed['tokenId']);

        if (! $token) {
            throw new RuntimeException('Invalid refresh token');
        }

        if ($token->revoked_at !== null) {
            throw new RuntimeException('Refresh token has been revoked');
        }

        if ($token->expires_at->isPast()) {
            throw new RuntimeException('Refresh token has expired. Please login again');
        }

        if (! Hash::check($parsed['tokenValue'], $token->token_hash)) {
            throw new RuntimeException('Invalid refresh token');
        }

        $user = User::query()->find($token->user_id);
        if (! $user) {
            throw new RuntimeException('User not found');
        }

        $token->forceFill(['revoked_at' => now()])->save();

        $refresh = $this->generateRefreshToken();
        $newExpiresAt = now()->addDays(self::REFRESH_TOKEN_DAYS);

        RefreshToken::query()->create([
            'id' => $refresh['tokenId'],
            'user_id' => $user->id,
            'token_hash' => Hash::make($refresh['tokenValue']),
            'expires_at' => $newExpiresAt,
            'revoked_at' => null,
            'issued_at' => now(),
            'issued_ip' => request()->ip(),
            'user_agent' => request()->userAgent() ?? 'unknown',
            'parent_token_id' => $token->id,
        ]);

        return [
            'success' => true,
            'accessToken' => $this->issueAccessToken($user, 15),
            'userId' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'refreshToken' => $refresh['tokenId'].'.'.$refresh['tokenValue'],
        ];
    }

    public function logout(array $payload): array
    {
        $refreshToken = (string) ($payload['refreshToken'] ?? '');

        if ($refreshToken === '') {
            throw new RuntimeException('Invalid refresh token');
        }

        $parsed = $this->parseRefreshToken($refreshToken);
        $token = RefreshToken::query()->find($parsed['tokenId']);

        if (! $token) {
            throw new RuntimeException('Invalid refresh token');
        }

        if (! Hash::check($parsed['tokenValue'], $token->token_hash)) {
            throw new RuntimeException('Invalid refresh token');
        }

        $token->forceFill(['revoked_at' => now()])->save();

        return [
            'success' => true,
            'message' => 'Logged out successfully.',
        ];
    }

    public function passwordResetInitiate(array $payload): array
    {
        $email = (string) ($payload['email'] ?? '');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException('Invalid email');
        }

        if (in_array($user->account_status, ['SUSPENDED', 'DEACTIVATED'], true)) {
            throw new RuntimeException(
                $user->account_status === 'SUSPENDED'
                    ? 'Your account has been suspended. Please contact support.'
                    : 'Your account has been deactivated. Please contact support.'
            );
        }

        $session = PasswordResetSession::query()->create([
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ?? 'unknown',
            'expires_at' => now()->addMinutes(self::PASSWORD_RESET_SESSION_MINUTES),
        ]);

        $otp = $this->generateOtp();
        UserOtp::query()->create([
            'user_id' => $user->id,
            'password_reset_session_id' => $session->id,
            'otp_hash' => Hash::make($otp),
            'mfa_method' => 'EMAIL_OTP',
            'purpose' => 'Password Reset OTP',
            'expires_at' => now()->addMinutes(self::OTP_MINUTES),
        ]);

        $this->sendOtpEmail(
            $user->email,
            'Password Reset Verification',
            "Your password reset verification code is {$otp}. It expires in 5 minutes.",
        );

        return [
            'resetSessionId' => $session->id,
            'otpDelivery' => $user->email,
            'expiresAt' => now()->addMinutes(self::OTP_MINUTES)->toISOString(),
        ];
    }

    public function passwordResetResendOtp(array $payload): array
    {
        $sessionId = (string) ($payload['resetSessionId'] ?? '');
        $session = PasswordResetSession::query()->find($sessionId);

        if (! $session) {
            throw new RuntimeException('Password reset session not found or expired');
        }

        if ($session->expires_at->isPast()) {
            throw new RuntimeException('Password reset session has expired. Please request a new one');
        }

        UserOtp::query()
            ->where('password_reset_session_id', $session->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $user = User::query()->find($session->user_id);
        if (! $user) {
            throw new RuntimeException('Invalid email');
        }

        $otp = $this->generateOtp();
        $otpRecord = UserOtp::query()->create([
            'user_id' => $user->id,
            'password_reset_session_id' => $session->id,
            'otp_hash' => Hash::make($otp),
            'mfa_method' => 'EMAIL_OTP',
            'purpose' => 'Password Reset OTP',
            'expires_at' => now()->addMinutes(self::OTP_MINUTES),
        ]);

        $this->sendOtpEmail(
            $user->email,
            'Password Reset Verification',
            "Your password reset verification code is {$otp}. It expires in 5 minutes.",
        );

        return [
            'resetSessionId' => $session->id,
            'otpDelivery' => $user->email,
            'expiresAt' => $otpRecord->expires_at->toISOString(),
        ];
    }

    public function passwordResetConfirm(array $payload): array
    {
        $sessionId = (string) ($payload['resetSessionId'] ?? '');
        $otp = (string) ($payload['otp'] ?? '');
        $newPassword = (string) ($payload['newPassword'] ?? '');

        $session = PasswordResetSession::query()->find($sessionId);

        if (! $session) {
            throw new RuntimeException('Password reset session not found or expired');
        }

        if ($session->expires_at->isPast()) {
            throw new RuntimeException('Password reset session has expired. Please request a new one');
        }

        $otpRecord = UserOtp::query()
            ->where('password_reset_session_id', $session->id)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (! $otpRecord) {
            throw new RuntimeException('OTP not found or does not belong to this password reset session');
        }

        if ($otpRecord->expires_at->isPast()) {
            throw new RuntimeException('OTP has expired');
        }

        if ($otpRecord->used_at !== null) {
            throw new RuntimeException('OTP has already been used');
        }

        if (! Hash::check($otp, $otpRecord->otp_hash)) {
            throw new RuntimeException('Invalid OTP');
        }

        $user = User::query()->find($session->user_id);
        if (! $user) {
            throw new RuntimeException('User not found');
        }

        DB::transaction(function () use ($session, $otpRecord, $user, $newPassword): void {
            $otpRecord->forceFill(['used_at' => now()])->save();

            $user->forceFill([
                'password_hash' => Hash::make($newPassword),
                'updated_at' => now(),
            ])->save();

            $session->forceFill(['completed_at' => now()])->save();

            RefreshToken::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });

        return [
            'success' => true,
            'message' => 'Password reset successful. You can now log in with your new password.',
        ];
    }
}
