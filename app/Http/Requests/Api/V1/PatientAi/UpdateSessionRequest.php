<?php

namespace App\Http\Requests\Api\V1\PatientAi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(['ACTIVE', 'ARCHIVED'])],
            'title' => ['sometimes', 'nullable', 'string', 'max:300'],
        ];
    }
}
