<?php

namespace App\Services\PatientAi;

use App\Contracts\PatientAi\RagClient;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Services\Patient\PatientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PatientAiService
{
    private const MAX_ACTIVE_SESSIONS = 50;
    private const MAX_MESSAGES_PER_SESSION = 200;

    public function __construct(
        private readonly RagClient $ragClient,
        private readonly PatientService $patientService,
    ) {
    }

    public function createSession(string $patientId, ?string $title = null): array
    {
        $activeSessions = AiChatSession::query()
            ->where('patient_id', $patientId)
            ->where('status', 'ACTIVE')
            ->count();

        if ($activeSessions >= self::MAX_ACTIVE_SESSIONS) {
            throw new RuntimeException(
                'Maximum of '.self::MAX_ACTIVE_SESSIONS.' active sessions reached. Please archive an existing session first.',
            );
        }

        $session = AiChatSession::query()->create([
            'patient_id' => $patientId,
            'status' => 'ACTIVE',
            'title' => $title,
            'message_count' => 0,
            'last_message_preview' => null,
        ]);

        return [
            'session' => $this->formatSession($session),
        ];
    }

    public function listSessions(string $patientId, ?string $status = null): array
    {
        $query = AiChatSession::query()
            ->where('patient_id', $patientId)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at');

        if (in_array($status, ['ACTIVE', 'ARCHIVED'], true)) {
            $query->where('status', $status);
        }

        return [
            'sessions' => $query->get()
                ->map(fn (AiChatSession $session) => $this->formatSession($session))
                ->all(),
        ];
    }

    public function getSession(string $patientId, string $sessionId): array
    {
        $session = AiChatSession::query()
            ->where('id', $sessionId)
            ->where('patient_id', $patientId)
            ->first();

        if (! $session) {
            throw new RuntimeException('Chat session not found.');
        }

        $messages = AiChatMessage::query()
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiChatMessage $message) => $this->formatMessage($message))
            ->all();

        return [
            'session' => $this->formatSession($session),
            'messages' => $messages,
        ];
    }

    public function updateSession(string $patientId, string $sessionId, array $data): array
    {
        $session = AiChatSession::query()
            ->where('id', $sessionId)
            ->where('patient_id', $patientId)
            ->first();

        if (! $session) {
            throw new RuntimeException('Chat session not found.');
        }

        $updates = [];

        if (array_key_exists('status', $data) && in_array($data['status'], ['ACTIVE', 'ARCHIVED'], true)) {
            $updates['status'] = $data['status'];
        }

        if (array_key_exists('title', $data)) {
            $updates['title'] = $data['title'];
        }

        if ($updates === []) {
            throw new RuntimeException('At least one field (status or title) is required.');
        }

        $session->fill($updates);
        $session->save();

        return [
            'session' => $this->formatSession($session->refresh()),
        ];
    }

    public function deleteAllSessions(string $patientId): array
    {
        $sessions = AiChatSession::query()->where('patient_id', $patientId)->get();
        $sessionIds = $sessions->pluck('id')->all();

        if ($sessionIds !== []) {
            AiChatMessage::query()->whereIn('session_id', $sessionIds)->delete();
        }

        $deletedCount = AiChatSession::query()->where('patient_id', $patientId)->delete();

        return [
            'message' => $deletedCount.' session(s) deleted.',
            'deletedCount' => $deletedCount,
        ];
    }

    public function deleteSession(string $patientId, string $sessionId): array
    {
        return $this->updateSession($patientId, $sessionId, ['status' => 'ARCHIVED']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendMessage(array $payload): array
    {
        if (! isset($payload['patientId'], $payload['sessionId'], $payload['message'])) {
            throw new RuntimeException('Missing required patient AI payload fields.');
        }

        $patientId = (string) $payload['patientId'];
        $sessionId = (string) $payload['sessionId'];
        $message = (string) $payload['message'];
        $recentMessages = $payload['recentMessages'] ?? [];
        $patientContext = $payload['patientContext'] ?? [];

        $session = AiChatSession::query()
            ->where('id', $sessionId)
            ->where('patient_id', $patientId)
            ->first();

        if (! $session) {
            throw new RuntimeException('Chat session not found.');
        }

        if ((int) $session->message_count >= self::MAX_MESSAGES_PER_SESSION) {
            throw new RuntimeException(
                'This session has reached the maximum of '.self::MAX_MESSAGES_PER_SESSION.' messages. Please start a new session.',
            );
        }

        $userMessage = AiChatMessage::query()->create([
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $message,
            'metadata' => null,
        ]);

        $autoTitle = $session->message_count === 0
            ? Str::limit($message, 100, '...')
            : null;

        DB::transaction(function () use ($session, $autoTitle): void {
            $session->message_count = (int) $session->message_count + 1;

            if (is_string($autoTitle) && $autoTitle !== '') {
                $session->title = $autoTitle;
            }

            $session->save();
        });

        $history = AiChatMessage::query()
            ->where('session_id', $sessionId)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->reverse()
            ->map(fn (AiChatMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        $autoPatientContext = $this->buildPatientContext($patientId);

        $ragPayload = [
            'patientId' => $patientId,
            'sessionId' => $sessionId,
            'message' => $message,
            'recentMessages' => is_array($recentMessages) && $recentMessages !== [] ? $recentMessages : $history,
            'patientContext' => array_replace_recursive(
                $autoPatientContext,
                is_array($patientContext) ? $patientContext : [],
            ),
        ];

        $startedAt = microtime(true);
        $ragResponse = $this->ragClient->chat($ragPayload);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $assistantContent = (string) ($ragResponse['assistantMessage'] ?? $ragResponse['content'] ?? '');

        if ($assistantContent === '') {
            throw new RuntimeException('The Python RAG service returned an empty assistant message.');
        }

        $assistantMessage = AiChatMessage::query()->create([
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $assistantContent,
            'metadata' => [
                'model' => $ragResponse['model'] ?? null,
                'latencyMs' => $ragResponse['latencyMs'] ?? $latencyMs,
                'sources' => $ragResponse['sources'] ?? [],
                'retrievalMetadata' => $ragResponse['retrievalMetadata'] ?? [],
            ],
        ]);

        $session->forceFill([
            'message_count' => (int) $session->message_count + 1,
            'last_message_preview' => Str::limit($assistantContent, 120, '...'),
        ])->save();

        $session = $session->refresh();

        return [
            'userMessage' => $this->formatMessage($userMessage),
            'assistantMessage' => $this->formatMessage($assistantMessage),
            'session' => [
                'id' => $sessionId,
                'messageCount' => (int) $session->message_count,
                'title' => $session->title,
            ],
            'meta' => [
                'model' => $ragResponse['model'] ?? null,
                'latencyMs' => $ragResponse['latencyMs'] ?? $latencyMs,
            ],
        ];
    }

    /**
     * Build a compact, RAG-friendly summary from the patient database.
     *
     * The Python service should receive enough clinical context to answer
     * questions safely without having to query Laravel directly.
     */
    private function buildPatientContext(string $patientId): array
    {
        $userId = DB::table('patients')
            ->where('id', $patientId)
            ->value('user_id');

        if (! is_string($userId) || $userId === '') {
            throw new RuntimeException('Patient profile not found.');
        }

        return [
            'medicalIdentity' => $this->patientService->medicalIdentity($userId),
            'medicalHistory' => $this->patientService->medicalHistory($userId),
            'healthJournalDiagnoses' => $this->patientService->healthJournalDiagnoses($userId),
            'reminderCounters' => $this->patientService->homeReminderCounters($userId),
            'todaySchedule' => $this->patientService->todaySchedule($userId),
            'notifications' => $this->patientService->notifications($userId),
        ];
    }

    private function formatSession(AiChatSession $session): array
    {
        return [
            'id' => $session->id,
            'patientId' => $session->patient_id,
            'status' => $session->status,
            'title' => $session->title,
            'messageCount' => (int) $session->message_count,
            'lastMessagePreview' => $session->last_message_preview,
            'createdAt' => $session->created_at,
            'updatedAt' => $session->updated_at,
        ];
    }

    private function formatMessage(AiChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'sessionId' => $message->session_id,
            'role' => $message->role,
            'content' => $message->content,
            'metadata' => $message->metadata,
            'createdAt' => $message->created_at,
        ];
    }
}
