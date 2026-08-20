<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PasswordResetConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resetSessionId' => ['required', 'uuid'],
            'otp' => ['required', 'digits:6'],
            'newPassword' => ['required', 'string', 'min:8'],
        ];
    }
}
