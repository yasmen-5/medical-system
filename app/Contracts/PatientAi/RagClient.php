<?php

namespace App\Contracts\PatientAi;

interface RagClient
{
    /**
     * Send a chat payload to the Python RAG service.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function chat(array $payload): array;
}
