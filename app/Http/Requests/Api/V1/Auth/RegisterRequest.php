<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([
                'PATIENT',
                'HEALTHCARE_PROVIDER',
                'LAB',
                'IMAGING_CENTER',
            ])],
            'phoneNumber' => ['sometimes', 'nullable', 'string', 'max:20'],
            'firstName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'middleName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'surName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', Rule::in(['MALE', 'FEMALE'])],
            'dateOfBirth' => ['sometimes', 'nullable', 'date'],
            'nationalId' => ['sometimes', 'nullable', 'regex:/^[0-9]{14}$/'],
            'bloodType' => ['sometimes', 'nullable', 'string', 'max:10'],
            'weightKg' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'heightCm' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'medicalLicenseNumber' => ['sometimes', 'nullable', 'string', 'max:100'],
            'specialization' => ['sometimes', 'nullable', 'string', 'max:100'],
            'workplaceName' => ['sometimes', 'nullable', 'string', 'max:300'],
            'workplaceAddress' => ['sometimes', 'nullable', 'string'],
            'centerName' => ['sometimes', 'nullable', 'string', 'max:300'],
            'registrationNumber' => ['sometimes', 'nullable', 'string', 'max:100'],
            'administratorFullName' => ['sometimes', 'nullable', 'string', 'max:300'],
            'centerAddress' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
