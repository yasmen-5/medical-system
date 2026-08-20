<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginVerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loginSessionId' => ['required', 'uuid'],
            'otp' => ['required', 'digits:6'],
            'platform' => ['required', Rule::in(['mobile', 'web'])],
        ];
    }
}
