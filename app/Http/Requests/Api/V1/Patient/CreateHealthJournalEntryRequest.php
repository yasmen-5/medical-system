<?php

namespace App\Http\Requests\Api\V1\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateHealthJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'diagnosisId' => ['required', 'string', 'max:64'],
            'patientOutcome' => ['required', Rule::in([
                'FULLY_RECOVERED',
                'IMPROVED',
                'NO_CHANGE',
                'WORSE',
            ])],
            'patientOutcomeDetails' => ['sometimes', 'nullable', 'string'],
            'painLevel' => ['required', 'integer', 'min:0', 'max:10'],
            'energyLevel' => ['required', 'integer', 'min:0', 'max:10'],
            'mood' => ['required', 'string', 'max:2000'],
        ];
    }
}
