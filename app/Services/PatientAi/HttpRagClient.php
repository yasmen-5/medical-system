<?php

namespace App\Services\PatientAi;

use App\Contracts\PatientAi\RagClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpRagClient implements RagClient
{
    public function chat(array $payload): array
    {
        $url = rtrim((string) (config('services.patient_ai.rag_service_url') ?? config('patient_ai.rag_service_url')), '/');
        $timeout = (int) (config('services.patient_ai.rag_service_timeout') ?? config('patient_ai.rag_service_timeout', 60));
        $apiKey = config('services.patient_ai.rag_service_api_key') ?? config('patient_ai.rag_service_api_key');

        if ($url === '') {
            throw new RuntimeException('RAG service URL is not configured.');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->timeout($timeout);

        if (is_string($apiKey) && $apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        try {
            $response = $request->post($url.'/chat', $payload);
            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'The Python RAG service returned an error response.',
                0,
                $exception,
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('The Python RAG service returned an invalid payload.');
        }

        return $data;
    }
}
