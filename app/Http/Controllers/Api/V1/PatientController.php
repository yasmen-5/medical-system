<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Patient\AddEmergencyContactRequest;
use App\Http\Requests\Api\V1\Patient\CreateHealthJournalEntryRequest;
use App\Http\Requests\Api\V1\Patient\UpdateReminderRequest;
use App\Services\Patient\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {
    }

    private function notImplemented(string $action): JsonResponse
    {
        return response()->json([
            'message' => "Patient action '{$action}' is not implemented yet.",
        ], 501);
    }

    public function medicalIdentity(Request $request): JsonResponse
    {
        return response()->json($this->patientService->medicalIdentity($this->resolveUserId($request)));
    }

    public function name(Request $request): JsonResponse
    {
        return response()->json($this->patientService->name($this->resolveUserId($request)));
    }

    public function medicalHistory(Request $request): JsonResponse
    {
        return response()->json($this->patientService->medicalHistory($this->resolveUserId($request)));
    }

    public function medicalHistoryEncounter(Request $request, string $encounterId): JsonResponse
    {
        return response()->json($this->patientService->medicalHistoryEncounter($this->resolveUserId($request), $encounterId));
    }

    public function reminders(Request $request): JsonResponse
    {
        return response()->json($this->patientService->reminders($this->resolveUserId($request)));
    }

    public function activeReminders(Request $request): JsonResponse
    {
        return response()->json($this->patientService->activeReminders($this->resolveUserId($request)));
    }

    public function homeReminderCounters(Request $request): JsonResponse
    {
        return response()->json($this->patientService->homeReminderCounters($this->resolveUserId($request)));
    }

    public function todaySchedule(Request $request): JsonResponse
    {
        return response()->json($this->patientService->todaySchedule($this->resolveUserId($request)));
    }

    public function updateReminder(UpdateReminderRequest $request, string $reminderId): JsonResponse
    {
        return response()->json($this->patientService->updateReminder($this->resolveUserId($request), $reminderId, $request->validated()));
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json($this->patientService->notifications($this->resolveUserId($request)));
    }

    public function pendingNotifications(Request $request): JsonResponse
    {
        return response()->json($this->patientService->pendingNotifications($this->resolveUserId($request)));
    }

    public function markNotificationRead(Request $request, string $notificationId): JsonResponse
    {
        return response()->json($this->patientService->markNotificationRead($this->resolveUserId($request), $notificationId));
    }

    public function healthJournalDiagnoses(Request $request): JsonResponse
    {
        return response()->json($this->patientService->healthJournalDiagnoses($this->resolveUserId($request)));
    }

    public function createHealthJournalNote(CreateHealthJournalEntryRequest $request): JsonResponse
    {
        return response()->json($this->patientService->createHealthJournalNote($this->resolveUserId($request), $request->validated()), 201);
    }

    public function healthJournalNoteSummary(Request $request): JsonResponse
    {
        return response()->json($this->patientService->healthJournalNoteSummary($this->resolveUserId($request)));
    }

    public function healthJournalNotes(Request $request, string $diagnosisId): JsonResponse
    {
        return response()->json($this->patientService->healthJournalNotes($this->resolveUserId($request), $diagnosisId));
    }

    public function uploadProfilePicture(Request $request): JsonResponse
    {
        $file = $request->file('profilePicture');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'profilePicture' => 'No file uploaded.',
            ]);
        }

        return response()->json($this->patientService->uploadProfilePicture($this->resolveUserId($request), $file));
    }

    public function profilePicture(Request $request): JsonResponse
    {
        return response()->json($this->patientService->profilePicture($this->resolveUserId($request)));
    }

    public function addEmergencyContact(AddEmergencyContactRequest $request): JsonResponse
    {
        return response()->json($this->patientService->addEmergencyContact($this->resolveUserId($request), $request->validated()), 201);
    }

    public function removeEmergencyContact(Request $request, string $contactId): JsonResponse
    {
        return response()->json($this->patientService->removeEmergencyContact($this->resolveUserId($request), $contactId));
    }

    private function resolveUserId(Request $request): string
    {
        $userId = $request->attributes->get('auth_user_id')
            ?? $request->input('userId')
            ?? $request->header('X-User-Id');

        if (! is_string($userId) || $userId === '') {
            throw ValidationException::withMessages([
                'userId' => 'The userId field is required for this request.',
            ]);
        }

        return $userId;
    }
}
