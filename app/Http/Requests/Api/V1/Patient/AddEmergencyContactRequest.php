<?php

namespace App\Http\Requests\Api\V1\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contactName' => ['required', 'string', 'max:200'],
            'phoneNumber' => ['required', 'string', 'max:20'],
            'relationship' => ['required', Rule::in([
                'PARENT',
                'SPOUSE',
                'SIBLING',
                'FRIEND',
                'CAREGIVER',
                'OTHER',
            ])],
            'isPrimary' => ['sometimes', 'boolean'],
        ];
    }
}
