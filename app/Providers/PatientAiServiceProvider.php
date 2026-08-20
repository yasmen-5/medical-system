<?php

namespace App\Providers;

use App\Contracts\PatientAi\RagClient;
use App\Services\PatientAi\HttpRagClient;
use Illuminate\Support\ServiceProvider;

class PatientAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RagClient::class, HttpRagClient::class);
    }
}
