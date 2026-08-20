<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('phone_number', 20)->nullable();
            $table->string('password_hash');
            $table->string('role', 32);
            $table->string('account_status', 32)->default('PENDING');
            $table->boolean('email_verified')->nullable();
            $table->string('mfa_method', 32)->default('NONE');
            $table->text('mfa_secret')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->uuid('suspended_by')->nullable();
            $table->text('suspention_reason')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->uuid('deactivated_by')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestampTz('locked_until')->nullable();
            $table->timestampsTz();

            $table->index(['id', 'account_status'], 'idx_users_pending_verification');
        });

        Schema::create('patients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100);
            $table->string('surname', 100);
            $table->string('gender', 16);
            $table->date('date_of_birth');
            $table->string('national_id', 50);
            $table->string('blood_type', 16)->nullable();
            $table->unsignedInteger('weight_kg')->nullable();
            $table->unsignedInteger('height_cm')->nullable();
            $table->timestampsTz();
        });

        Schema::create('healthcare_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100);
            $table->string('surname', 100);
            $table->string('gender', 16);
            $table->date('date_of_birth');
            $table->string('national_id', 50);
            $table->string('medical_license_number', 100);
            $table->string('specialization', 100);
            $table->string('workplace_name', 300)->nullable();
            $table->text('workplace_address')->nullable();
            $table->timestampsTz();
        });

        Schema::create('laboratories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('lab_name', 300);
            $table->string('registration_number', 100);
            $table->string('administrator_full_name', 300);
            $table->text('lab_address')->nullable();
            $table->timestampsTz();
        });

        Schema::create('imaging_centers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('center_name', 300);
            $table->string('registration_number', 100);
            $table->string('administrator_full_name', 300);
            $table->text('center_address')->nullable();
            $table->timestampsTz();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('file_type', 64)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->timestampTz('uploaded_at')->nullable();
        });

        Schema::create('login_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('registration_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('role', 32);
            $table->json('registration_data');
            $table->json('registration_documents');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('password_reset_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('user_otps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('login_session_id')->nullable();
            $table->uuid('register_session_id')->nullable();
            $table->uuid('password_reset_session_id')->nullable();
            $table->string('otp_hash');
            $table->string('mfa_method', 32);
            $table->string('purpose', 50);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('issued_at')->useCurrent();
            $table->string('issued_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('parent_token_id')->nullable();
        });

        Schema::create('ai_chat_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->string('status', 32)->default('ACTIVE');
            $table->string('title', 300)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->text('last_message_preview')->nullable();
            $table->timestampsTz();
            $table->index(['patient_id', 'status'], 'idx_ai_chat_sessions_patient');
        });

        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->string('role', 32);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['session_id', 'created_at'], 'idx_ai_chat_messages_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('user_otps');
        Schema::dropIfExists('password_reset_sessions');
        Schema::dropIfExists('registration_sessions');
        Schema::dropIfExists('login_sessions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('imaging_centers');
        Schema::dropIfExists('laboratories');
        Schema::dropIfExists('healthcare_providers');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('users');
    }
};
