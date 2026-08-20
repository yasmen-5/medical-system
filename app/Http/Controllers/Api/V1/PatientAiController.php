<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PatientAi\CreateSessionRequest;
use App\Http\Requests\Api\V1\PatientAi\SendMessageRequest;
use App\Http\Requests\Api\V1\PatientAi\UpdateSessionRequest;
use App\Services\PatientAi\PatientAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PatientAiController extends Controller
{
    public function __construct(
        private readonly PatientAiService $patientAiService,
    ) {
    }

    private function notImplemented(string $action): JsonResponse
    {
        return response()->json([
            'message' => "Patient AI action '{$action}' is not implemented yet.",
        ], 501);
    }

    public function createSession(CreateSessionRequest $request): JsonResponse
    {
        return response()->json(
            $this->patientAiService->createSession(
                $this->resolvePatientId($request),
                $request->validated('title'),
            ),
            201
        );
    }

    public function listSessions(Request $request): JsonResponse
    {
        return response()->json(
            $this->patientAiService->listSessions(
                $this->resolvePatientId($request),
                $request->query('status'),
            )
        );
    }

    public function getSession(Request $request, string $sessionId): JsonResponse
    {
        return response()->json(
            $this->patientAiService->getSession(
                $this->resolvePatientId($request),
                $sessionId,
            )
        );
    }

    public function updateSession(UpdateSessionRequest $request, string $sessionId): JsonResponse
    {
        return response()->json(
            $this->patientAiService->updateSession(
                $this->resolvePatientId($request),
                $sessionId,
                $request->validated(),
            )
        );
    }

    public function deleteAllSessions(Request $request): JsonResponse
    {
        return response()->json(
            $this->patientAiService->deleteAllSessions($this->resolvePatientId($request))
        );
    }

    public function deleteSession(Request $request, string $sessionId): JsonResponse
    {
        return response()->json(
            $this->patientAiService->deleteSession(
                $this->resolvePatientId($request),
                $sessionId,
            )
        );
    }

    public function sendMessage(SendMessageRequest $request, string $sessionId): JsonResponse
    {
        $payload = $request->validated();
        $payload['patientId'] = $this->resolvePatientId($request);
        $payload['sessionId'] = $sessionId;

        return response()->json(
            $this->patientAiService->sendMessage($payload)
        );
    }

    private function resolvePatientId(Request $request): string
    {
        $patientId = $request->attributes->get('auth_patient_id')
            ?? $request->input('patientId')
            ?? $request->header('X-Patient-Id');

        if (! is_string($patientId) || $patientId === '') {
            throw ValidationException::withMessages([
                'patientId' => 'The patientId field is required for this request.',
            ]);
        }

        return $patientId;
    }
}
