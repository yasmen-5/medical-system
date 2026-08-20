<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use RuntimeException;

class ResolvePatientAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return response()->json(['message' => 'Authentication token is required.'], 401);
        }

        try {
            $payload = $this->decodeToken($token);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        $userId = (string) ($payload['sub'] ?? '');
        $role = (string) ($payload['role'] ?? '');
        $email = (string) ($payload['email'] ?? '');

        if ($userId === '' || $email === '' || $role === '') {
            return response()->json(['message' => 'Invalid authentication token.'], 401);
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return response()->json(['message' => 'Authentication user not found.'], 401);
        }

        if ($user->role !== 'PATIENT') {
            return response()->json(['message' => 'Patient access only.'], 403);
        }

        if (in_array($user->account_status, ['SUSPENDED', 'DEACTIVATED', 'REJECTED'], true)) {
            return response()->json(['message' => 'Patient account is not active.'], 403);
        }

        $patientId = DB::table('patients')
            ->where('user_id', $user->id)
            ->value('id');

        if (! is_string($patientId) || $patientId === '') {
            return response()->json(['message' => 'Patient profile not found.'], 404);
        }

        $request->attributes->set('auth_user_id', $user->id);
        $request->attributes->set('auth_user_email', $user->email);
        $request->attributes->set('auth_user_role', $user->role);
        $request->attributes->set('auth_patient_id', $patientId);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = (string) $request->bearerToken();

        return $header !== '' ? $header : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException('Invalid authentication token format.');
        }

        [$encodedPayload, $signature] = $parts;
        $secret = (string) env('JWT_ACCESS_SECRET', config('app.key'));
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid authentication token signature.');
        }

        $json = $this->base64UrlDecode($encodedPayload);

        if ($json === false) {
            throw new RuntimeException('Invalid authentication token payload.');
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid authentication token payload.');
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);

        if ($expiresAt > 0 && now()->timestamp > $expiresAt) {
            throw new RuntimeException('Authentication token has expired.');
        }

        return $payload;
    }

    private function base64UrlDecode(string $value): string|false
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
