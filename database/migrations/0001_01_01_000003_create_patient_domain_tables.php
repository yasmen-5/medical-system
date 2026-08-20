<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_emergency_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->string('contact_name', 200);
            $table->string('phone_number', 20);
            $table->string('relationship', 32);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();
        });

        Schema::create('patient_allergies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->string('allergen_name', 500)->nullable();
            $table->string('severity', 32)->nullable();
            $table->text('reaction_description')->nullable();
            $table->uuid('diagnosed_by')->nullable();
            $table->date('diagnosed_date')->nullable();
            $table->timestampsTz();
        });

        Schema::create('clinical_encounters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id')->nullable();
            $table->uuid('hcp_id')->nullable();
            $table->timestampTz('encounter_date')->nullable();
            $table->text('location_address')->nullable();
            $table->timestampTz('next_appointment_date')->nullable();
            $table->text('appointment_notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('encounter_symptoms_complaints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id');
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->timestampsTz();
        });

        Schema::create('diagnoses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id')->nullable();
            $table->uuid('patient_id')->nullable();
            $table->string('icd11_code', 20)->nullable();
            $table->string('icd11_title', 500)->nullable();
            $table->text('clinical_description')->nullable();
            $table->boolean('is_chronic')->default(false);
            $table->string('status', 32)->nullable();
            $table->timestampTz('diagnosed_date')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->uuid('resolved_by_hcp_id')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'patient_id'], 'uq_diagnosis_patient');
        });

        Schema::create('patient_health_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('diagnosis_id');
            $table->date('note_date')->useCurrent();
            $table->string('patient_outcome', 32)->nullable();
            $table->text('patient_outcome_details')->nullable();
            $table->text('mood')->nullable();
            $table->unsignedTinyInteger('pain_level')->nullable();
            $table->unsignedTinyInteger('energy_level')->nullable();
            $table->timestampsTz();
        });

        Schema::create('medications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id')->nullable();
            $table->uuid('patient_id')->nullable();
            $table->uuid('diagnosis_id')->nullable();
            $table->uuid('prescribed_by_hcp_id')->nullable();
            $table->string('medication_name', 500)->nullable();
            $table->decimal('dosage_amount', 10, 2)->nullable();
            $table->string('dosage_unit', 32)->nullable();
            $table->string('form', 32)->nullable();
            $table->string('frequency', 500)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('instructions')->nullable();
            $table->timestampTz('prescribed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('ref_imaging_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('ref_body_parts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('ref_test_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('ref_specimen_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('medical_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id')->nullable();
            $table->uuid('patient_id')->nullable();
            $table->uuid('ordered_by_hcp_id')->nullable();
            $table->string('order_type', 32)->nullable();
            $table->string('order_status', 32)->nullable();
            $table->timestampTz('ordered_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('lab_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('medical_order_id')->unique();
            $table->unsignedInteger('test_type_id')->nullable();
            $table->unsignedInteger('specimen_type_id')->nullable();
            $table->boolean('fasting_required')->nullable();
            $table->string('priority', 32)->nullable();
            $table->text('clinical_indication')->nullable();
        });

        Schema::create('lab_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->uuid('patient_id')->nullable();
            $table->uuid('lab_id')->nullable();
            $table->json('result_data')->nullable();
            $table->text('additional_notes')->nullable();
            $table->timestampTz('uploaded_at')->nullable();
            $table->uuid('uploaded_by_user_id')->nullable();
        });

        Schema::create('lab_result_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('lab_result_id')->nullable();
            $table->uuid('document_id')->nullable();
        });

        Schema::create('imaging_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('medical_order_id')->unique();
            $table->unsignedInteger('imaging_type_id')->nullable();
            $table->unsignedInteger('body_part_id')->nullable();
            $table->boolean('contrast_used')->nullable();
            $table->string('priority', 32)->nullable();
            $table->text('clinical_indication')->nullable();
        });

        Schema::create('imaging_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->uuid('patient_id')->nullable();
            $table->uuid('imaging_center_id')->nullable();
            $table->text('study_description')->nullable();
            $table->text('findings')->nullable();
            $table->timestampTz('uploaded_at')->nullable();
            $table->uuid('uploaded_by_user_id')->nullable();
        });

        Schema::create('imaging_result_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('imaging_result_id')->nullable();
            $table->uuid('document_id')->nullable();
        });

        Schema::create('reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->string('reminder_type', 32);
            $table->uuid('medication_id')->nullable();
            $table->uuid('medical_order_id')->nullable();
            $table->uuid('encounter_id')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestampTz('appointment_at')->nullable();
            $table->time('reminder_time')->default('09:00:00');
            $table->json('custom_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('notification_type', 32);
            $table->string('status', 32)->default('PENDING');
            $table->string('title', 500);
            $table->text('message');
            $table->uuid('reminder_id')->nullable();
            $table->timestampTz('scheduled_for');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('patient_access_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('grantee_user_id');
            $table->string('access_type', 32)->nullable();
            $table->string('access_status', 32)->nullable();
            $table->timestampsTz();
        });

        Schema::create('patient_permission_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('grantee_user_id')->nullable();
            $table->string('access_type', 32)->nullable();
            $table->string('access_status', 32)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_permission_tokens');
        Schema::dropIfExists('patient_access_grants');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('imaging_result_documents');
        Schema::dropIfExists('imaging_results');
        Schema::dropIfExists('imaging_orders');
        Schema::dropIfExists('lab_result_documents');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('medical_orders');
        Schema::dropIfExists('ref_specimen_types');
        Schema::dropIfExists('ref_test_types');
        Schema::dropIfExists('ref_body_parts');
        Schema::dropIfExists('ref_imaging_types');
        Schema::dropIfExists('medications');
        Schema::dropIfExists('patient_health_notes');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('encounter_symptoms_complaints');
        Schema::dropIfExists('clinical_encounters');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patient_emergency_contacts');
    }
};
