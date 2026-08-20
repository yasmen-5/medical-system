<?php

namespace App\Services\Patient;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PatientService
{
    private function requirePatientId(string $userId): string
    {
        $patientId = DB::table('patients')
            ->where('user_id', $userId)
            ->value('id');

        if (! is_string($patientId) || $patientId === '') {
            throw new RuntimeException('Patient profile not found.');
        }

        return $patientId;
    }

    private function requirePatientByUserId(string $userId): object
    {
        $patient = DB::table('patients')->where('user_id', $userId)->first();

        if (! $patient) {
            throw new RuntimeException('Patient profile not found.');
        }

        return $patient;
    }

    public function medicalIdentity(string $userId): array
    {
        $patientId = $this->requirePatientId($userId);
        $patient = DB::table('patients')->where('id', $patientId)->first();

        $profilePicture = DB::table('documents')
            ->where('user_id', $userId)
            ->where('file_type', 'PROFILE_PICTURE')
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->first();

        $activeDiagnoses = DB::table('diagnoses as d')
            ->leftJoin('clinical_encounters as ce', 'ce.id', '=', 'd.encounter_id')
            ->leftJoin('healthcare_providers as h', 'h.id', '=', 'ce.hcp_id')
            ->where('d.patient_id', $patientId)
            ->where(function ($query): void {
                $query->where('d.status', 'ACTIVE')->orWhereNull('d.status');
            })
            ->orderByDesc(DB::raw('COALESCE(d.diagnosed_date, d.created_at)'))
            ->get([
                'd.id as diagnosis_id',
                'd.icd11_code',
                'd.icd11_title',
                DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)) as diagnosed_by"),
                'd.diagnosed_date',
            ]);

        $currentMedications = DB::table('medications as m')
            ->leftJoin('diagnoses as d', function ($join): void {
                $join->on('d.id', '=', 'm.diagnosis_id')
                    ->on('d.patient_id', '=', 'm.patient_id');
            })
            ->leftJoin('healthcare_providers as h', 'h.id', '=', 'm.prescribed_by_hcp_id')
            ->where('m.patient_id', $patientId)
            ->where(function ($query): void {
                $query->whereNull('m.start_date')->orWhere('m.start_date', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('m.end_date')->orWhere('m.end_date', '>=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('m.diagnosis_id')->orWhere(function ($nested): void {
                    $nested->whereNotNull('d.id')
                        ->where(function ($statusQuery): void {
                            $statusQuery->where('d.status', 'ACTIVE')->orWhereNull('d.status');
                        });
                });
            })
            ->orderByDesc(DB::raw('COALESCE(m.start_date, m.created_at)'))
            ->get([
                'm.id as medication_id',
                'm.medication_name',
                'm.dosage_amount',
                'm.dosage_unit',
                'm.form',
                'm.frequency',
                'm.instructions',
                'm.start_date',
                'm.end_date',
                DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)) as prescribed_by"),
                'm.prescribed_at',
            ]);

        $allergies = DB::table('patient_allergies as a')
            ->leftJoin('healthcare_providers as h', 'h.id', '=', 'a.diagnosed_by')
            ->where('a.patient_id', $patientId)
            ->orderByDesc(DB::raw('COALESCE(a.diagnosed_date, a.created_at)'))
            ->get([
                'a.id as allergy_id',
                'a.allergen_name',
                'a.severity',
                'a.reaction_description',
                DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)) as diagnosed_by"),
                'a.diagnosed_date',
            ]);

        $chronicConditions = DB::table('diagnoses as d')
            ->leftJoin('clinical_encounters as ce', 'ce.id', '=', 'd.encounter_id')
            ->leftJoin('healthcare_providers as h', 'h.id', '=', 'ce.hcp_id')
            ->where('d.patient_id', $patientId)
            ->where('d.is_chronic', true)
            ->where(function ($query): void {
                $query->where('d.status', 'ACTIVE')->orWhereNull('d.status');
            })
            ->orderByDesc(DB::raw('COALESCE(d.diagnosed_date, d.created_at)'))
            ->get([
                'd.id as diagnosis_id',
                'd.icd11_code',
                'd.icd11_title',
                DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)) as diagnosed_by"),
                'd.diagnosed_date',
            ]);

        $emergencyContacts = DB::table('patient_emergency_contacts')
            ->where('patient_id', $patientId)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get([
                'id as contact_id',
                'contact_name',
                'relationship',
                'phone_number',
                'is_primary',
            ]);

        $weightKg = $patient?->weight_kg !== null ? (int) $patient->weight_kg : null;
        $heightCm = $patient?->height_cm !== null ? (int) $patient->height_cm : null;

        return [
            'basicInfo' => [
                'firstName' => $patient->first_name,
                'middleName' => $patient->middle_name,
                'surname' => $patient->surname,
                'fullName' => trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->surname]))),
                'age' => $this->calculateAge($patient->date_of_birth),
                'gender' => $patient->gender,
                'bloodType' => $patient->blood_type,
                'weightKg' => $weightKg,
                'heightCm' => $heightCm,
                'bmi' => $this->calculateBmi($weightKg, $heightCm),
                'profilePictureUrl' => $profilePicture?->file_path,
            ],
            'activeDiagnoses' => $activeDiagnoses->map(fn ($row) => [
                'diagnosisId' => $row->diagnosis_id,
                'icd11Code' => $row->icd11_code,
                'icd11Title' => $row->icd11_title,
                'diagnosedBy' => $row->diagnosed_by,
                'diagnosedDate' => $row->diagnosed_date,
            ])->all(),
            'currentMedications' => $currentMedications->map(fn ($row) => [
                'medicationId' => $row->medication_id,
                'medicationName' => $row->medication_name,
                'dosageAmount' => $row->dosage_amount !== null ? (float) $row->dosage_amount : null,
                'dosageUnit' => $row->dosage_unit,
                'form' => $row->form,
                'frequency' => $row->frequency,
                'instructions' => $row->instructions,
                'startDate' => $this->formatDateOnly($row->start_date),
                'endDate' => $this->formatDateOnly($row->end_date),
                'prescribedBy' => $row->prescribed_by,
                'prescribedAt' => $row->prescribed_at,
            ])->all(),
            'allergies' => $allergies->map(fn ($row) => [
                'allergyId' => $row->allergy_id,
                'allergenName' => $row->allergen_name,
                'severity' => $row->severity,
                'reactionDescription' => $row->reaction_description,
                'diagnosedBy' => $row->diagnosed_by,
                'diagnosedDate' => $row->diagnosed_date,
            ])->all(),
            'chronicConditions' => $chronicConditions->map(fn ($row) => [
                'diagnosisId' => $row->diagnosis_id,
                'icd11Code' => $row->icd11_code,
                'icd11Title' => $row->icd11_title,
                'diagnosedBy' => $row->diagnosed_by,
                'diagnosedDate' => $row->diagnosed_date,
            ])->all(),
            'emergencyContacts' => $emergencyContacts->map(fn ($row) => [
                'contactId' => $row->contact_id,
                'contactName' => $row->contact_name,
                'relationship' => $row->relationship,
                'phoneNumber' => $row->phone_number,
                'isPrimary' => (bool) $row->is_primary,
            ])->all(),
        ];
    }

    public function name(string $userId): array
    {
        $patient = $this->requirePatientByUserId($userId);

        return [
            'name' => [
                'firstName' => $patient->first_name,
                'middleName' => $patient->middle_name,
                'surname' => $patient->surname,
                'fullName' => trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->surname]))),
            ],
        ];
    }

    public function medicalHistory(string $userId): array
    {
        $patientId = $this->requirePatientId($userId);

        $encounters = DB::table('clinical_encounters as ce')
            ->leftJoin('healthcare_providers as h', 'h.id', '=', 'ce.hcp_id')
            ->leftJoin('diagnoses as d', function ($join): void {
                $join->on('d.encounter_id', '=', 'ce.id');
            })
            ->where('ce.patient_id', $patientId)
            ->groupBy('ce.id', 'h.first_name', 'h.middle_name', 'h.surname', 'h.specialization', 'ce.encounter_date', 'ce.location_address', 'ce.created_at', 'd.icd11_code', 'd.icd11_title')
            ->orderByDesc('ce.encounter_date')
            ->orderByDesc('ce.created_at')
            ->get([
                'ce.id as encounter_id',
                DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)) as doctor_name"),
                DB::raw('h.specialization as doctor_speciality'),
                'ce.encounter_date',
                'ce.location_address',
                DB::raw('MIN(d.icd11_code) as icd11_code'),
                DB::raw('MIN(d.icd11_title) as icd11_title'),
            ]);

        return [
            'encounters' => $encounters->map(fn ($row) => [
                'encounterId' => $row->encounter_id,
                'doctorName' => $row->doctor_name,
                'doctorSpeciality' => $row->doctor_speciality,
                'encounterDate' => $row->encounter_date,
                'location' => $row->location_address,
                'primaryDiagnosis' => $row->icd11_code || $row->icd11_title ? [
                    'icd11Code' => $row->icd11_code,
                    'icd11Title' => $row->icd11_title,
                ] : null,
            ])->all(),
        ];
    }

    public function medicalHistoryEncounter(string $userId, string $encounterId): array
    {
        $patientId = $this->requirePatientId($userId);

        $summary = DB::table('clinical_encounters as ce')
            ->leftJoin('healthcare_providers as h', 'h.id', '=', 'ce.hcp_id')
            ->leftJoin('diagnoses as d', function ($join): void {
                $join->on('d.encounter_id', '=', 'ce.id');
            })
            ->where('ce.id', $encounterId)
            ->where('ce.patient_id', $patientId)
            ->groupBy('ce.id', 'h.first_name', 'h.middle_name', 'h.surname', 'h.specialization', 'ce.encounter_date', 'ce.location_address', 'ce.created_at')
            ->first([
                'ce.id as encounter_id',
                DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)) as doctor_name"),
                DB::raw('h.specialization as doctor_speciality'),
                'ce.encounter_date',
                'ce.location_address',
                DB::raw('MIN(d.icd11_code) as icd11_code'),
                DB::raw('MIN(d.icd11_title) as icd11_title'),
            ]);

        if (! $summary) {
            throw new RuntimeException('Encounter not found.');
        }

        $symptoms = DB::table('encounter_symptoms_complaints')
            ->where('encounter_id', $encounterId)
            ->orderBy('created_at')
            ->get(['title', 'description']);

        $diagnoses = DB::table('diagnoses')
            ->where('encounter_id', $encounterId)
            ->orderBy('created_at')
            ->get(['icd11_code', 'icd11_title', 'clinical_description']);

        $medications = DB::table('medications')
            ->where('encounter_id', $encounterId)
            ->orderBy('created_at')
            ->get(['medication_name', 'dosage_amount', 'dosage_unit', 'form', 'frequency', 'instructions', 'start_date', 'end_date']);

        return [
            'encounter' => [
                'encounterId' => $summary->encounter_id,
                'doctorName' => $summary->doctor_name,
                'doctorSpeciality' => $summary->doctor_speciality,
                'encounterDate' => $summary->encounter_date,
                'location' => $summary->location_address,
                'primaryDiagnosis' => $summary->icd11_code || $summary->icd11_title ? [
                    'icd11Code' => $summary->icd11_code,
                    'icd11Title' => $summary->icd11_title,
                ] : null,
                'symptomsAndComplaints' => $symptoms->map(fn ($row) => [
                    'title' => $row->title,
                    'description' => $row->description,
                ])->all(),
                'diagnoses' => $diagnoses->map(fn ($row) => [
                    'icd11Code' => $row->icd11_code,
                    'icd11Title' => $row->icd11_title,
                    'clinicalDescription' => $row->clinical_description,
                ])->all(),
                'prescribedMedications' => $medications->map(fn ($row) => [
                    'medicationName' => $row->medication_name,
                    'dosageAmount' => $row->dosage_amount !== null ? (float) $row->dosage_amount : null,
                    'dosageUnit' => $row->dosage_unit,
                    'form' => $row->form,
                    'frequency' => $row->frequency,
                    'instructions' => $row->instructions,
                    'startDate' => $this->formatDateOnly($row->start_date),
                    'endDate' => $this->formatDateOnly($row->end_date),
                ])->all(),
            ],
        ];
    }

    public function reminders(string $userId): array
    {
        return $this->listActiveReminders($userId);
    }

    public function activeReminders(string $userId): array
    {
        return $this->listActiveReminders($userId);
    }

    public function homeReminderCounters(string $userId): array
    {
        $patientId = $this->requirePatientId($userId);

        $counters = DB::table('reminders as r')
            ->leftJoin('medical_orders as mo', 'mo.id', '=', 'r.medical_order_id')
            ->where('r.patient_id', $patientId)
            ->selectRaw("COUNT(*) FILTER (WHERE r.reminder_type = 'MEDICATION' AND r.is_active = TRUE AND r.starts_at <= CURRENT_DATE AND (r.ends_at IS NULL OR r.ends_at >= CURRENT_DATE))::INT AS medication_reminders")
            ->selectRaw("COUNT(*) FILTER (WHERE r.reminder_type = 'APPOINTMENT' AND r.is_active = TRUE AND r.appointment_at >= NOW())::INT AS upcoming_appointments")
            ->selectRaw("COUNT(*) FILTER (WHERE r.reminder_type = 'MEDICAL_ORDER' AND r.is_active = TRUE AND mo.order_status IN ('PENDING','IN_PROGRESS'))::INT AS pending_test_orders")
            ->first();

        return [
            'counters' => [
                'medicationReminders' => (int) ($counters->medication_reminders ?? 0),
                'upcomingAppointments' => (int) ($counters->upcoming_appointments ?? 0),
                'pendingTestOrders' => (int) ($counters->pending_test_orders ?? 0),
            ],
        ];
    }

    public function todaySchedule(string $userId): array
    {
        $patientId = $this->requirePatientId($userId);

        $rows = DB::select(
            "
            SELECT schedule_item_id, kind, time_label, what
            FROM (
                SELECT r.id AS schedule_item_id, r.created_at, 1 AS kind_sort, r.reminder_type AS kind, TO_CHAR(r.reminder_time, 'HH12:MI AM') AS time_label, m.medication_name AS what, (CURRENT_DATE::timestamp + r.reminder_time) AS scheduled_at
                FROM reminders r
                INNER JOIN medications m ON m.id = r.medication_id
                WHERE r.patient_id = ? AND r.reminder_type = 'MEDICATION' AND r.is_active = TRUE AND r.starts_at <= CURRENT_DATE AND (r.ends_at IS NULL OR r.ends_at >= CURRENT_DATE)
                UNION ALL
                SELECT r.id AS schedule_item_id, r.created_at, 2 AS kind_sort, r.reminder_type AS kind, TO_CHAR(r.appointment_at, 'HH12:MI AM') AS time_label,
                    CASE WHEN NULLIF(TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)), '') IS NOT NULL THEN CONCAT('Appointment with Dr. ', TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname))) ELSE 'Appointment' END AS what,
                    r.appointment_at AS scheduled_at
                FROM reminders r
                INNER JOIN clinical_encounters ce ON ce.id = r.encounter_id
                LEFT JOIN healthcare_providers h ON h.id = ce.hcp_id
                WHERE r.patient_id = ? AND r.reminder_type = 'APPOINTMENT' AND r.is_active = TRUE AND r.appointment_at::date = CURRENT_DATE
                UNION ALL
                SELECT r.id AS schedule_item_id, r.created_at, 3 AS kind_sort, r.reminder_type AS kind, TO_CHAR(r.reminder_time, 'HH12:MI AM') AS time_label,
                    CASE WHEN mo.order_type = 'LABORATORY' THEN CONCAT('Lab: ', COALESCE(rt.name, 'Test Order')) WHEN mo.order_type = 'IMAGING' THEN CONCAT('Imaging: ', COALESCE(ri.name, 'Test Order')) ELSE COALESCE(rt.name, ri.name, 'Test Order') END AS what,
                    (CURRENT_DATE::timestamp + r.reminder_time) AS scheduled_at
                FROM reminders r
                INNER JOIN medical_orders mo ON mo.id = r.medical_order_id
                LEFT JOIN lab_orders lo ON lo.medical_order_id = mo.id
                LEFT JOIN ref_test_types rt ON rt.id = lo.test_type_id
                LEFT JOIN imaging_orders io ON io.medical_order_id = mo.id
                LEFT JOIN ref_imaging_types ri ON ri.id = io.imaging_type_id
                WHERE r.patient_id = ? AND r.reminder_type = 'MEDICAL_ORDER' AND r.is_active = TRUE AND mo.order_status IN ('PENDING','IN_PROGRESS')
            ) schedule_items
            ORDER BY scheduled_at ASC, kind_sort ASC, created_at ASC
            ",
            [$patientId, $patientId, $patientId]
        );

        return [
            'schedule' => collect($rows)->map(fn ($row) => [
                'time' => $row->time_label,
                'what' => $row->what,
                'kind' => $row->kind,
            ])->all(),
        ];
    }

    public function updateReminder(string $userId, string $reminderId, array $payload): array
    {
        $patientId = $this->requirePatientId($userId);

        $reminder = DB::table('reminders')
            ->where('id', $reminderId)
            ->where('patient_id', $patientId)
            ->first(['id', 'reminder_type']);

        if (! $reminder) {
            throw new RuntimeException('Reminder not found.');
        }

        $reminderTime = $payload['reminder_time'] ?? $payload['reminderTime'] ?? null;
        $customDays = $payload['custom_days'] ?? $payload['customDays'] ?? null;
        $isActive = $payload['is_active'] ?? $payload['isActive'] ?? null;
        $hasCustomDays = array_key_exists('custom_days', $payload) || array_key_exists('customDays', $payload);

        if ($reminderTime === null && ! $hasCustomDays && $isActive === null) {
            throw new RuntimeException('At least one reminder update field is required.');
        }

        if (($reminderTime !== null || $hasCustomDays) && $reminder->reminder_type !== 'MEDICATION') {
            throw new RuntimeException('Only medication reminders can customize reminder_time or custom_days.');
        }

        if ($isActive !== null) {
            if ($isActive !== false) {
                throw new RuntimeException('Only dismissing a reminder with is_active=false is supported.');
            }

            if ($reminder->reminder_type !== 'MEDICAL_ORDER') {
                throw new RuntimeException('Only medical order reminders can be dismissed by the patient.');
            }
        }

        $updated = DB::table('reminders')
            ->where('id', $reminderId)
            ->where('patient_id', $patientId)
            ->update([
                'reminder_time' => $reminderTime,
                'custom_days' => $hasCustomDays ? $customDays : DB::raw('custom_days'),
                'is_active' => $isActive ?? DB::raw('is_active'),
                'dismissed_at' => $isActive === false ? now() : DB::raw('dismissed_at'),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new RuntimeException('Reminder not found.');
        }

        return [
            'reminder' => DB::table('reminders')
                ->where('id', $reminderId)
                ->first(),
        ];
    }

    public function notifications(string $userId): array
    {
        return [
            'notifications' => DB::table('notifications')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->orderByDesc('scheduled_for')
                ->get()
                ->map(fn ($row) => $this->formatNotification($row))
                ->all(),
        ];
    }

    public function pendingNotifications(string $userId): array
    {
        $rows = DB::select(
            "
            WITH due_notifications AS (
                SELECT id
                FROM notifications
                WHERE user_id = ? AND status = 'PENDING' AND scheduled_for <= NOW()
                ORDER BY scheduled_for ASC, created_at ASC
                FOR UPDATE SKIP LOCKED
            )
            UPDATE notifications n
            SET status = 'SENT', sent_at = NOW()
            FROM due_notifications d
            WHERE n.id = d.id
            RETURNING n.id AS notification_id, n.notification_type, n.status, n.title, n.message, n.reminder_id, n.scheduled_for, n.sent_at, n.read_at, n.created_at
            ",
            [$userId]
        );

        return [
            'notifications' => collect($rows)->map(fn ($row) => [
                'notificationId' => $row->notification_id,
                'notificationType' => $row->notification_type,
                'status' => $row->status,
                'title' => $row->title,
                'message' => $row->message,
                'reminderId' => $row->reminder_id,
                'scheduledFor' => $row->scheduled_for,
                'sentAt' => $row->sent_at,
                'readAt' => $row->read_at,
                'createdAt' => $row->created_at,
            ])->all(),
        ];
    }

    public function markNotificationRead(string $userId, string $notificationId): array
    {
        $updated = DB::table('notifications')
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->update([
                'status' => 'READ',
                'read_at' => now(),
            ]);

        if ($updated === 0) {
            throw new RuntimeException('Notification not found.');
        }

        return [
            'notification' => $this->formatNotification(
                DB::table('notifications')->where('id', $notificationId)->first()
            ),
        ];
    }

    public function healthJournalDiagnoses(string $userId): array
    {
        $patientId = $this->requirePatientId($userId);

        return [
            'diagnoses' => DB::table('diagnoses as d')
                ->leftJoin('clinical_encounters as ce', 'ce.id', '=', 'd.encounter_id')
                ->leftJoin('healthcare_providers as h', 'h.id', '=', 'ce.hcp_id')
                ->where('d.patient_id', $patientId)
                ->where(function ($query): void {
                    $query->where('d.status', 'ACTIVE')->orWhereNull('d.status');
                })
                ->orderByDesc('d.is_chronic')
                ->orderByDesc(DB::raw('COALESCE(d.diagnosed_date, d.created_at)'))
                ->get([
                    'd.id as diagnosis_id',
                    'd.icd11_code',
                    'd.icd11_title',
                    'd.clinical_description',
                    'd.is_chronic',
                    DB::raw("TRIM(CONCAT_WS(' ', h.first_name, h.middle_name, h.surname)) as diagnosed_by"),
                    'd.diagnosed_date',
                ])
                ->map(fn ($row) => [
                    'diagnosisId' => $row->diagnosis_id,
                    'icd11Code' => $row->icd11_code,
                    'icd11Title' => $row->icd11_title,
                    'clinicalDescription' => $row->clinical_description,
                    'isChronic' => (bool) $row->is_chronic,
                    'diagnosedBy' => $row->diagnosed_by,
                    'diagnosedDate' => $row->diagnosed_date,
                ])->all(),
        ];
    }

    public function createHealthJournalNote(string $userId, array $payload): array
    {
        $patientId = $this->requirePatientId($userId);
        $diagnosisId = (string) ($payload['diagnosisId'] ?? '');

        $diagnosis = DB::table('diagnoses')
            ->where('id', $diagnosisId)
            ->where('patient_id', $patientId)
            ->where(function ($query): void {
                $query->where('status', 'ACTIVE')->orWhereNull('status');
            })
            ->first();

        if (! $diagnosis) {
            throw new RuntimeException('The selected diagnosis is not active for this patient.');
        }

        $noteId = (string) Str::uuid();
        DB::table('patient_health_notes')->insert([
            'id' => $noteId,
            'patient_id' => $patientId,
            'diagnosis_id' => $diagnosisId,
            'note_date' => now()->toDateString(),
            'patient_outcome' => $payload['patientOutcome'] ?? null,
            'patient_outcome_details' => $payload['patientOutcomeDetails'] ?? null,
            'mood' => $payload['mood'] ?? null,
            'pain_level' => $payload['painLevel'] ?? null,
            'energy_level' => $payload['energyLevel'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'entry' => [
                'noteId' => $noteId,
                'patientId' => $patientId,
                'diagnosisId' => $diagnosisId,
                'noteDate' => now()->toDateString(),
                'patientOutcome' => $payload['patientOutcome'] ?? null,
                'patientOutcomeDetails' => $payload['patientOutcomeDetails'] ?? null,
                'mood' => $payload['mood'] ?? null,
                'painLevel' => $payload['painLevel'] ?? null,
                'energyLevel' => $payload['energyLevel'] ?? null,
                'createdAt' => now(),
                'updatedAt' => now(),
                'diagnosis' => [
                    'diagnosisId' => $diagnosis->id,
                    'icd11Code' => $diagnosis->icd11_code,
                    'icd11Title' => $diagnosis->icd11_title,
                    'clinicalDescription' => $diagnosis->clinical_description,
                    'isChronic' => (bool) $diagnosis->is_chronic,
                    'diagnosedBy' => null,
                    'diagnosedDate' => $diagnosis->diagnosed_date,
                ],
            ],
            'healthSnapshot' => [
                'note' => 'Your journal entry was saved successfully.',
            ],
        ];
    }

    public function healthJournalNoteSummary(string $userId): array
    {
        $patientId = $this->requirePatientId($userId);

        return [
            'diagnoses' => DB::select(
                "
                SELECT
                    d.id AS diagnosis_id,
                    d.icd11_code,
                    d.icd11_title,
                    d.is_chronic,
                    COUNT(n.id)::INT AS total_entries,
                    MAX(n.note_date) AS last_entry_date,
                    last_note.patient_outcome AS last_patient_outcome,
                    last_note.pain_level AS last_pain_level,
                    last_note.energy_level AS last_energy_level,
                    last_note.mood AS last_mood,
                    last_note.created_at AS last_note_created_at
                FROM diagnoses d
                INNER JOIN patient_health_notes n ON n.diagnosis_id = d.id
                LEFT JOIN LATERAL (
                    SELECT patient_outcome, pain_level, energy_level, mood, created_at
                    FROM patient_health_notes
                    WHERE diagnosis_id = d.id AND patient_id = ?
                    ORDER BY note_date DESC, created_at DESC
                    LIMIT 1
                ) last_note ON TRUE
                WHERE d.patient_id = ?
                GROUP BY d.id, d.icd11_code, d.icd11_title, d.is_chronic, last_note.patient_outcome, last_note.pain_level, last_note.energy_level, last_note.mood, last_note.created_at
                ORDER BY last_entry_date DESC NULLS LAST
                ",
                [$patientId, $patientId]
            ),
        ];
    }

    public function healthJournalNotes(string $userId, string $diagnosisId): array
    {
        $patientId = $this->requirePatientId($userId);
        $diagnosis = DB::table('diagnoses')
            ->where('id', $diagnosisId)
            ->where('patient_id', $patientId)
            ->first();

        if (! $diagnosis) {
            throw new RuntimeException('Diagnosis not found for this patient.');
        }

        return [
            'diagnosis' => [
                'diagnosisId' => $diagnosis->id,
                'icd11Code' => $diagnosis->icd11_code,
                'icd11Title' => $diagnosis->icd11_title,
                'isChronic' => (bool) $diagnosis->is_chronic,
                'status' => $diagnosis->status,
            ],
            'notes' => DB::table('patient_health_notes')
                ->where('patient_id', $patientId)
                ->where('diagnosis_id', $diagnosisId)
                ->orderByDesc('note_date')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($row) => [
                    'noteId' => $row->id,
                    'noteDate' => $row->note_date,
                    'patientOutcome' => $row->patient_outcome,
                    'patientOutcomeDetails' => $row->patient_outcome_details,
                    'mood' => $row->mood,
                    'painLevel' => $row->pain_level !== null ? (int) $row->pain_level : null,
                    'energyLevel' => $row->energy_level !== null ? (int) $row->energy_level : null,
                    'createdAt' => $row->created_at,
                ])->all(),
        ];
    }

    public function uploadProfilePicture(string $userId, UploadedFile $file): array
    {
        $patient = $this->requirePatientByUserId($userId);

        $path = $file->storePubliclyAs(
            'profile-pictures',
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'public'
        );

        DB::table('documents')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'file_type' => 'PROFILE_PICTURE',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        return [
            'message' => 'Profile picture uploaded successfully.',
        ];
    }

    public function profilePicture(string $userId): array
    {
        $document = DB::table('documents')
            ->where('user_id', $userId)
            ->where('file_type', 'PROFILE_PICTURE')
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->first();

        if (! $document) {
            throw new RuntimeException('No profile picture set.');
        }

        $fullPath = Storage::disk('public')->path($document->file_path);

        if (! file_exists($fullPath)) {
            throw new RuntimeException('Profile picture file not found on disk.');
        }

        return [
            'filePath' => $document->file_path,
            'fileName' => $document->file_name,
            'mimeType' => $document->mime_type,
        ];
    }

    public function addEmergencyContact(string $userId, array $payload): array
    {
        $patientId = $this->requirePatientId($userId);

        return DB::transaction(function () use ($patientId, $payload): array {
            if (! empty($payload['isPrimary'])) {
                DB::table('patient_emergency_contacts')
                    ->where('patient_id', $patientId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $contactId = (string) Str::uuid();
            DB::table('patient_emergency_contacts')->insert([
                'id' => $contactId,
                'patient_id' => $patientId,
                'contact_name' => $payload['contactName'],
                'phone_number' => $payload['phoneNumber'],
                'relationship' => $payload['relationship'],
                'is_primary' => (bool) ($payload['isPrimary'] ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'contact' => DB::table('patient_emergency_contacts')
                    ->where('id', $contactId)
                    ->first(),
            ];
        });
    }

    public function removeEmergencyContact(string $userId, string $contactId): array
    {
        $patientId = $this->requirePatientId($userId);
        $deleted = DB::table('patient_emergency_contacts')
            ->where('id', $contactId)
            ->where('patient_id', $patientId)
            ->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Emergency contact not found.');
        }

        return [
            'message' => 'Emergency contact removed.',
        ];
    }

    public function getPatientIdByUserId(string $userId): ?string
    {
        return DB::table('patients')->where('user_id', $userId)->value('id');
    }

    private function listActiveReminders(string $userId): array
    {
        $patientId = $this->requirePatientId($userId);

        $rows = DB::table('reminders as r')
            ->leftJoin('medications as m', 'm.id', '=', 'r.medication_id')
            ->leftJoin('healthcare_providers as med_h', 'med_h.id', '=', 'm.prescribed_by_hcp_id')
            ->leftJoin('clinical_encounters as ce', 'ce.id', '=', 'r.encounter_id')
            ->leftJoin('healthcare_providers as appt_h', 'appt_h.id', '=', 'ce.hcp_id')
            ->leftJoin('medical_orders as mo', 'mo.id', '=', 'r.medical_order_id')
            ->leftJoin('healthcare_providers as order_h', 'order_h.id', '=', 'mo.ordered_by_hcp_id')
            ->leftJoin('lab_orders as lo', 'lo.medical_order_id', '=', 'mo.id')
            ->leftJoin('ref_test_types as rt', 'rt.id', '=', 'lo.test_type_id')
            ->leftJoin('ref_specimen_types as rs', 'rs.id', '=', 'lo.specimen_type_id')
            ->leftJoin('imaging_orders as io', 'io.medical_order_id', '=', 'mo.id')
            ->leftJoin('ref_imaging_types as ri', 'ri.id', '=', 'io.imaging_type_id')
            ->leftJoin('ref_body_parts as rb', 'rb.id', '=', 'io.body_part_id')
            ->where('r.patient_id', $patientId)
            ->where('r.is_active', true)
            ->orderBy('r.reminder_type')
            ->orderByRaw('COALESCE(r.appointment_at, r.starts_at::timestamp, r.created_at) ASC')
            ->get();

        $reminders = $rows->map(fn ($row) => $this->formatReminder($row))->all();

        return [
            'reminders' => $reminders,
            'grouped' => [
                'appointments' => array_values(array_filter($reminders, fn ($reminder) => $reminder['reminderType'] === 'APPOINTMENT')),
                'medications' => array_values(array_filter($reminders, fn ($reminder) => $reminder['reminderType'] === 'MEDICATION')),
                'medicalOrders' => array_values(array_filter($reminders, fn ($reminder) => $reminder['reminderType'] === 'MEDICAL_ORDER')),
            ],
        ];
    }

    private function formatReminder(object $row): array
    {
        $base = [
            'reminderId' => $row->reminder_id,
            'reminderType' => $row->reminder_type,
            'startsAt' => $row->starts_at,
            'endsAt' => $row->ends_at,
            'appointmentAt' => $row->appointment_at,
            'reminderTime' => $row->reminder_time,
            'customDays' => $row->custom_days,
            'isActive' => (bool) $row->is_active,
            'dismissedAt' => $row->dismissed_at,
            'createdAt' => $row->created_at,
            'updatedAt' => $row->updated_at,
        ];

        if ($row->reminder_type === 'MEDICATION') {
            return $base + [
                'medication' => [
                    'medicationId' => $row->medication_id,
                    'name' => $row->medication_name,
                    'dosageAmount' => $row->dosage_amount !== null ? (float) $row->dosage_amount : null,
                    'dosageUnit' => $row->dosage_unit,
                    'form' => $row->form,
                    'frequency' => $row->frequency,
                    'startDate' => $row->start_date,
                    'endDate' => $row->end_date,
                    'prescribedBy' => $row->medication_prescribed_by,
                ],
            ];
        }

        if ($row->reminder_type === 'APPOINTMENT') {
            return $base + [
                'appointment' => [
                    'encounterId' => $row->encounter_id,
                    'doctorName' => $row->appointment_doctor_name,
                    'location' => $row->location_address,
                    'notes' => $row->appointment_notes,
                ],
            ];
        }

        return $base + [
            'medicalOrder' => [
                'medicalOrderId' => $row->medical_order_id,
                'orderType' => $row->order_type,
                'orderName' => $row->order_name,
                'priority' => $row->order_priority,
                'status' => $row->order_status,
                'orderedAt' => $row->ordered_at,
                'orderedBy' => $row->order_doctor_name,
                'specimenType' => $row->specimen_type,
                'bodyPart' => $row->body_part,
            ],
        ];
    }

    private function formatNotification(object $row): array
    {
        return [
            'notificationId' => $row->notification_id,
            'notificationType' => $row->notification_type,
            'status' => $row->status,
            'title' => $row->title,
            'message' => $row->message,
            'reminderId' => $row->reminder_id,
            'scheduledFor' => $row->scheduled_for,
            'sentAt' => $row->sent_at,
            'readAt' => $row->read_at,
            'createdAt' => $row->created_at,
        ];
    }

    private function calculateAge(string $dateOfBirth): int
    {
        return (int) now()->diffInYears($dateOfBirth);
    }

    private function calculateBmi(?int $weightKg, ?int $heightCm): ?float
    {
        if ($weightKg === null || $heightCm === null || $heightCm <= 0) {
            return null;
        }

        $heightInMeters = $heightCm / 100;

        return round($weightKg / ($heightInMeters * $heightInMeters), 2);
    }

    private function formatDateOnly(null|string|\DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return substr($value, 0, 10);
        }

        return $value->format('Y-m-d');
    }
}
