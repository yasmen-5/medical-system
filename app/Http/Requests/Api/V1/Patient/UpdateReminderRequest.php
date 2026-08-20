<?php

namespace App\Http\Requests\Api\V1\Patient;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reminder_time' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'reminderTime' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'custom_days' => ['sometimes', 'nullable', 'array', 'max:7'],
            'custom_days.*' => ['integer', 'min:1', 'max:7'],
            'customDays' => ['sometimes', 'nullable', 'array', 'max:7'],
            'customDays.*' => ['integer', 'min:1', 'max:7'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'isActive' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
