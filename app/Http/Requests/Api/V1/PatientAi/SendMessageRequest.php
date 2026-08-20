<?php

namespace App\Http\Requests\Api\V1\PatientAi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'recentMessages' => ['sometimes', 'array'],
            'recentMessages.*.role' => ['required_with:recentMessages', Rule::in(['system', 'user', 'assistant'])],
            'recentMessages.*.content' => ['required_with:recentMessages', 'string'],
            'patientContext' => ['sometimes', 'array'],
        ];
    }
}
