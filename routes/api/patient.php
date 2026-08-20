<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PatientAiController;
use App\Http\Controllers\Api\V1\PatientController;
use Illuminate\Support\Facades\Route;

Route::prefix('patient')->group(function (): void {
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

    Route::middleware('patient.access')->group(function (): void {
        Route::get('medical-identity', [PatientController::class, 'medicalIdentity']);
        Route::get('name', [PatientController::class, 'name']);
        Route::get('medical-history', [PatientController::class, 'medicalHistory']);
        Route::get('medical-history/{encounterId}', [PatientController::class, 'medicalHistoryEncounter']);
        Route::get('reminders', [PatientController::class, 'reminders']);
        Route::get('reminders/active', [PatientController::class, 'activeReminders']);
        Route::get('home/reminders/counters', [PatientController::class, 'homeReminderCounters']);
        Route::get('home/today-schedule', [PatientController::class, 'todaySchedule']);
        Route::patch('reminders/{reminderId}', [PatientController::class, 'updateReminder']);
        Route::get('notifications', [PatientController::class, 'notifications']);
        Route::get('notifications/pending', [PatientController::class, 'pendingNotifications']);
        Route::patch('notifications/{notificationId}/read', [PatientController::class, 'markNotificationRead']);
        Route::get('health-journal/diagnoses', [PatientController::class, 'healthJournalDiagnoses']);
        Route::post('health-journal/notes', [PatientController::class, 'createHealthJournalNote']);
        Route::get('health-journal/notes', [PatientController::class, 'healthJournalNoteSummary']);
        Route::get('health-journal/notes/{diagnosisId}', [PatientController::class, 'healthJournalNotes']);
        Route::post('profile-picture', [PatientController::class, 'uploadProfilePicture']);
        Route::get('profile-picture', [PatientController::class, 'profilePicture']);
        Route::post('emergency-contacts', [PatientController::class, 'addEmergencyContact']);
        Route::delete('emergency-contacts/{contactId}', [PatientController::class, 'removeEmergencyContact']);

        Route::prefix('ai')->group(function (): void {
            Route::post('chat/sessions', [PatientAiController::class, 'createSession']);
            Route::get('chat/sessions', [PatientAiController::class, 'listSessions']);
            Route::get('chat/sessions/{sessionId}', [PatientAiController::class, 'getSession']);
            Route::patch('chat/sessions/{sessionId}', [PatientAiController::class, 'updateSession']);
            Route::delete('chat/sessions', [PatientAiController::class, 'deleteAllSessions']);
            Route::delete('chat/sessions/{sessionId}', [PatientAiController::class, 'deleteSession']);
            Route::post('chat/sessions/{sessionId}/messages', [PatientAiController::class, 'sendMessage']);
        });
    });
});
