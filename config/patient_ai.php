<?php

return [
    'rag_service_url' => env('RAG_SERVICE_URL', 'http://127.0.0.1:9000'),
    'rag_service_timeout' => (int) env('RAG_SERVICE_TIMEOUT', 60),
    'rag_service_api_key' => env('RAG_SERVICE_API_KEY'),
    'fallback_provider' => env('PATIENT_AI_FALLBACK_PROVIDER', null),
];
